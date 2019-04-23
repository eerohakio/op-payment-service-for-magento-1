<?php
class Op_Checkout_Model_OpcheckoutPayment extends Mage_Payment_Model_Method_Abstract
{

    protected $_code = "opcheckout";
    protected $_formBlockType = 'opcheckout/form';
    protected $_infoBlockType = 'opcheckout/payment_info';
    
    protected $_isGateway = false;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_isInitializeNeeded = true;
    protected $_canRefundInvoicePartial = true;
    protected $_checkoutApi;

    public function createFormBlock($name)
    {
    }

    public function authorize_capture($payment, $amount)
    {
        Mage::log('authorize capture called', null, 'op_checkout.log', true);
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();
        Mage::log('capture called', null, 'op_checkout.log', true);

        if ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT
            || $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING
        ) {
            return $this;
        }

        $orderAdditionalInformation = $payment->getAdditionalInformation();
        $captureData = $orderAdditionalInformation["capture_data"];
        if(!is_array($captureData)) {
            Mage::throwException(Mage::helper('opcheckout')->__('No Capture Data'));
        }

        if (!$order->getId()) {
            Mage::throwException(Mage::helper('opcheckout')->__('No such order.'));
        }

        if ($order->getBaseTotalDue() == 0) {
            Mage::throwException(Mage::helper('opcheckout')->__('Order already paid.'));
        }

        if($order->hasInvoices())
        {
            Mage::throwException(Mage::helper('opcheckout')->__('Order already invoiced.'));
        }

                $rawdata = [
                    "orderNo" => $captureData["checkout-reference"],
                    "stamp" => $captureData["checkout-stamp"],
                    "method" => $captureData["checkout-provider"]
                ];

            $payment->setTransactionId($captureData["checkout-transaction-id"]);
            $payment->setIsTransactionClosed(false);
            $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $rawdata);
            $amount = $captureData["checkout-amount"]/100;
            $payment->registerCaptureNotification($amount);
            $payment->save();
            $payment->getOrder()->save();

        return $this;

    }

    public function getConfigPaymentAction() {
        return 'authorize_capture';
    }

    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }

    public function refund(Varien_Object $payment, $amount)
    {
        $this->_checkoutApi = Mage::getModel('opcheckout_api/checkout');

        if (!$payment->getParentTransactionId()) {
            Mage::throwException(Mage::helper('opcheckout')->__('Invalid transaction ID.'));
        }

        $body = $this->buildRefundRequest($amount);

        try {
            $this->postRefundRequest($payment, $body);

            $transactionId = $payment->getParentTransactionId()."-refund-".time();

            $payment->setTransactionId($transactionId)
                ->setIsTransactionClosed(1);

            $rawdata = [
                "amount" => $amount
            ];

            $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $rawdata);
            $payment->setShouldCloseParentTransaction(!$payment->getCreditmemo()->getInvoice()->canRefund());

            return $this;

        }  catch (Exception $e) {
            $error = $e->getMessage();
            Mage::log($error, 2, 'opcheckout_critical.log');

        }

        Mage::throwException(Mage::helper('opcheckout')->__('Refund failed.'));
    }


    protected function postRefundRequest($payment, $body)
    {
        $transactionId = $payment->getParentTransactionId();

        $uri = '/payments/' . $transactionId . '/refund';

        $bodyJson = json_encode($body);

        $response = $this->_checkoutApi->getResponse(
            $uri,
            '',
            'post',
            $transactionId,
            $bodyJson
        );

        $status = $response['status'];
        $data = $response['data'];

        if ($status === 201) {
            return true;
        } elseif (($status === 422 || $status === 400) && $this->postRefundRequestEmail($payment, $body)) {
            // TODO: 422 replaced with 400 ? should we add 4xx check here ?
            return true;
        } else {
            //TODO: DEAL WITH ERROR ! DON'T JUST LOG IT !
            Mage::log($response, 2, 'opcheckout_critical.log');
            return false;
        }
    }


    protected function postRefundRequestEmail($payment, $body)
    {

        $transactionId = $payment->getParentTransactionId();

        $uri = '/payments/' . $transactionId . '/refund/email';
        $body['email'] = $payment->getOrder()->getBillingAddress()->getEmail();
        $body = json_encode($body);

        $response = $this->_checkoutApi->getResponse(
            $uri,
            '',
            'post',
            $transactionId,
            $body
        );
        $status = $response['status'];
        $data = $response['data'];

        if ($status === 201) {
            return true;
        } else {
            //TODO: FIX LOGGER
            Mage::log($response, 2, 'opcheckout_critical.log');
            return false;
        }
    }


    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl("opcheckout/payment/selectbank", array("_secure" => true));
    }


    public function assignData($data)
    {
        parent::assignData($data);
        $data = Mage::app()->getRequest()->getPost('opcheckout');

        $info = $this->getInfoInstance();
        $info->setAdditionalInformation(
            'opcheckout_payment_method',
            $data['payment_methods']);

        $skipBankSelection = Mage::helper('opcheckout')->skipBankSelection();


        if(!$skipBankSelection && empty($info->getAdditionalInformation('opcheckout_payment_method'))){
            Mage::throwException(Mage::helper('opcheckout')->__('Please select payment method.'));
        }

        return $this;
    }

    protected function buildRefundRequest($amount)
    {
        $storeUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, $secure = null);

        $body = [
            'amount' => $amount * 100,
            'callbackUrls' => [
                'success' => $storeUrl,
                'cancel' => $storeUrl,
            ],
        ];

        return $body;
    }


}