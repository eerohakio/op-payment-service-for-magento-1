<?php
class Op_Checkout_Model_Opcheckout extends Mage_Core_Model_Abstract
{

    public function getPaymentData($order)
    {
        return true;
    }

    public function processPayment($payment)
    {
        return true;
    }

    public function validatePayment($paymentParams)
    {
    }


    public function cancelOrderAndActivateQuote($order)
    {
        if ($order && $order->getPayment()->getMethod() == Mage::getModel('opcheckout/opcheckoutPayment')->getCode() && !$order->getInvoiceCollection()->count()) {
            $orderModel = Mage::getModel('sales/order')->load($order->getId());
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
            $quote->setIsActive(true)->save();
            $orderModel->cancel();
            $orderModel->setStatus('canceled');
            $orderModel->save();
        }
    }
}
