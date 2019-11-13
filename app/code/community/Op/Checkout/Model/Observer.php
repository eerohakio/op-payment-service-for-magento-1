<?php
class Op_Checkout_Model_Observer extends Mage_Core_Model_Abstract
{

    public function choosePayment($observer)
    {
    }

    public function addUnCancelOrderButton(Varien_Event_Observer $observer)
    {
        $block = Mage::app()->getLayout()->getBlock('sales_order_edit');
        if (!$block) {
            return $this;
        }
        $order = Mage::registry('current_order');
        $rescue_order_url   = Mage::helper("adminhtml")->getUrl(
            "adminhtml/opcheckout/rescueorder",
            array('order_id' => $order->getId())
        );

        $refund_payment_url   = Mage::helper("adminhtml")->getUrl(
            "adminhtml/opcheckout/refund",
            array('order_id' => $order->getId())
        );

        $orderAdditionalInformation = $order->getPayment()->getAdditionalInformation();

        if (!$order->hasInvoices() &&
            is_array($orderAdditionalInformation["capture_data"]) &&
                ($order->getState() == Mage_Sales_Model_Order::STATE_CANCELED ||
                $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING)
            ) {
            $block->addButton(
                'rescue_order_button_id',
                array(
                    'label'   => Mage::helper('opcheckout')->__('Rescue Order'),
                    'onclick' => 'setLocation(\'' . $rescue_order_url . '\')',
                    'class'   => 'go'
                )
            );
        }

        return $this;
    }

    /** Handle uncompleted payment made by current customer before creating new
     * @param $observer
     */
    public function processLastPayment($observer)
    {
        $order = Mage::getModel("sales/order")->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());

        if ($order->getId() && $order->getPayment()->getMethodInstance()->getCode() == 'opcheckout') {
            if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW) {
                if ($order->getCustomerEmail() == $observer->getEvent()->getOrder()->getCustomerEmail()) {
                    $order->cancel();
                    $order->setStatus('canceled');
                    $order->save();
                }
            }
        }
    }
}