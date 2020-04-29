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

    /**
     * @param  Mage_Sales_Model_Order $order
     * @throws Exception
     */
    public function cancelOrderAndActivateQuote($order)
    {
        if ($order && $order->getPayment()->getMethod() == Mage::getModel('opcheckout/opcheckoutPayment')->getCode() && !$order->hasInvoices()) {
            $quoteId = $order->getQuoteId();
            $order
                ->cancel()
                ->save();

            /** @var Mage_Sales_Model_Quote $quote */
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            $quote
                ->setIsActive(true)
                ->setReservedOrderId(null)
                ->save();

            /** @var Mage_Checkout_Model_Session $checkoutSession */
            $checkoutSession = Mage::getSingleton('checkout/session');
            $checkoutSession->replaceQuote($quote);

            /** @var Mage_Checkout_Model_Cart $cart */
            $cart = Mage::getSingleton('checkout/cart');
            $cart
                ->setQuote($quote)
                ->save();

        }
    }
}
