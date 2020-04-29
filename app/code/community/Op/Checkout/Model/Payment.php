<?php


class Op_Checkout_Model_Payment extends Mage_Core_Model_Abstract
{

    private $opcheckoutApi;

    public function __construct()
    {
        parent::__construct();
        $this->opcheckoutApi = Mage::getModel('opcheckout_api/checkout');
    }

    public function getResponseData($order)
    {
        $uri = '/payments';
        $method = 'post';
        $response = $this->opcheckoutApi->getResponse($uri, $order, $method);
        return $response['data'];
    }

    public function getFormAction($responseData, $paymentMethodId = null)
    {
        $returnUrl = '';

        foreach ($responseData->providers as $provider) {
            if ($provider->id == $paymentMethodId) {
                $returnUrl = $provider->url;
            }
        }

        return $returnUrl;
    }

    public function getFormFields($responseData, $paymentMethodId = null)
    {
        $formFields = [];

        foreach ($responseData->providers as $provider) {
            if ($provider->id == $paymentMethodId) {
                foreach ($provider->parameters as $parameter) {
                    $formFields[$parameter->name] = $parameter->value;
                }
            }
        }

        return $formFields;
    }

    public function verifyPayment($signature, $status, $params)
    {
        if ($this->opcheckoutApi->getMerchantId() != $params["checkout-account"]) {
            Mage::throwException(Mage::helper('opcheckout')->__('wrong merchant id'));
        }

        $hmac = $this->opcheckoutApi->calculateHmac($params, '', $this->opcheckoutApi->getMerchantSecret());

        return ($signature === $hmac && ($status === 'ok' || $status === 'pending'));
    }

    public function validatePayment($params)
    {
        $order = Mage::getModel("sales/order")->loadByIncrementId($params["checkout-reference"]);

        $checkoutPendingStatus = false;
        if (!$order->getId()) {
            Mage::throwException(Mage::helper('opcheckout')->__('No such order.'));
        }

        $orderIsCanceled = false;
        if ($order->getState() == Mage_Sales_Model_Order::STATE_CANCELED) {
            Mage::helper('opcheckout')->notifyCanceledOrder($order);
            $orderIsCanceled = true;
        }

        if ($params["checkout-status"] == 'pending') {
            if ($order->getStatus() == 'pending_opcheckout') {
                return 'recovery';
            }

            $checkoutPendingStatus = true;
            $orderIsCanceled = true;
        }

        if ($order->getBaseTotalDue() == 0) {
            $order->setState(
                $order->getState(),
                $order->getStatus(),
                Mage::helper('opcheckout')->__('OP Payment Service tried to confirm already paid order. No actions required.'),
                null
            )->save();
            return true;
        }

        $rawdata = [
            "orderNo" => $params["checkout-reference"],
            "stamp" => $params["checkout-stamp"],
            "method" => $params["checkout-provider"]
        ];

        $orderPayment = $order->getPayment();
        if ($orderIsCanceled) {
            $paymentAdditionalInformation = $orderPayment->getAdditionalInformation();
            $captureData = ["capture_data" => $params];
            $newAi = array_merge($paymentAdditionalInformation, $captureData);
            $orderPayment->setAdditionalInformation($newAi);
            $orderPayment->save();
        } else {
            $orderPayment->setTransactionId($params["checkout-transaction-id"]);
            $orderPayment->setIsTransactionClosed(false);
            $orderPayment->setTransactionAdditionalInfo(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                $rawdata
            );
            $orderPayment->save();
        }

        if ($order->hasInvoices()) {
            $invoice = $order->getInvoiceCollection()->getFirstItem();
        } else {
            if ($order->canInvoice() && !$checkoutPendingStatus) {
                $invoice = $order->prepareInvoice();
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->register();
            }
        }

        $msg = '';
        if ($orderIsCanceled) {
            $msg = $checkoutPendingStatus == true ?
                'OP Payment Service payment was verified order with PENDING STATUS. By using payment/bank:' :
                'OP Payment Service payment was verified CANCELED order. By using payment/bank:';
        } else {
            try {
                $invoice->pay();
                $invoice->save();
            } catch (Exception $e) {
                Mage::log($e->getMessage(), null, 'op_checkout.log');
            }
            $msg = 'OP Payment Service payment was verified. By using payment/bank:';
        }

        $order->setState(
            Mage_Sales_Model_Order::STATE_PROCESSING,
            'processing',
            Mage::helper('opcheckout')->__($msg) . $params['checkout-provider'],
            null
        )->save();

        if ($orderIsCanceled && $checkoutPendingStatus == true) {
            $order->setStatus('pending_opcheckout');
        }

        $order->setEmailSent(true);

        try {
            $order->sendNewOrderEmail();
            $order->save();
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'op_checkout.log');
        }

        if ($orderIsCanceled) {
            return "recovery";
        }

        try {
            $invoice->setEmailSent(true);
            $invoice->sendEmail();
            $invoice->save();

            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transactionSave->save();

        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'op_checkout.log');
        }

        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
        $quote->setIsActive(false)->save();

        return $invoice;
    }
}
