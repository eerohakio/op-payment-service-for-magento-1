<?php
class Op_Checkout_PaymentController extends Mage_Core_Controller_Front_Action
{
    public function selectBankAction()
    {
        $checkoutPayment = Mage::getSingleton('opcheckout/Payment');
        $skipBankSelection = Mage::helper('opcheckout')->skipBankSelection();
        $order = Mage::getModel("sales/order")
            ->loadByIncrementId(Mage::getSingleton('checkout/session')
                ->getLastRealOrderId());

        $payment = $order->getPayment();
        $selectedPaymentMethodRaw = $payment["additional_information"]["opcheckout_payment_method"];
        $selectedPaymentMethodId = preg_replace('/[0-9]{1,2}$/', '', $selectedPaymentMethodRaw);

        if ($skipBankSelection) {
            $selectedPaymentMethodId = 'opcheckout';
        }
        $order->setState(
            $order->getState(),
            $order->getStatus(),
            Mage::helper('opcheckout')->__('Customer is redirected to: '.$selectedPaymentMethodId),
            null
        )->save();

        try {
            $responseData = $checkoutPayment->getResponseData($order);
        } catch (Exception $exception) {
            Mage::getModel('opcheckout/opcheckout')->cancelOrderAndActivateQuote($order);
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/cart'))->sendResponse();
        }

        $formData = $checkoutPayment->getFormFields($responseData, $selectedPaymentMethodId);
        $formAction = $checkoutPayment->getFormAction($responseData, $selectedPaymentMethodId);

        if (is_object($responseData)) {

            if ($skipBankSelection) {
                $payment_url = $responseData->href;
                Mage::app()->getResponse()->setRedirect($payment_url)->sendResponse();
                exit;
            }

            $this->loadLayout();
            $block = $this->getLayout()->createBlock('opcheckout/opcheckout');
            $block->setData($formData);
            $block->setFormAction($formAction);
            $data = $block->toHtml();
            echo $data;
        } else {
            Mage::getModel('opcheckout/opcheckout')->cancelOrderAndActivateQuote($order);
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/cart'))->sendResponse();
        }
        exit;
    }
}
