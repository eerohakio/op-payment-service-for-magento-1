<?php

class Op_Checkout_Adminhtml_OpcheckoutController extends Mage_Adminhtml_Controller_Action
{
    public function testAction()
    {
    }

    public function rescueorderAction()
    {
        $current_admin_user_id = Mage::getSingleton('admin/session')->getUser()->getId();


        $id = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($id);

        $order->setState(
            Mage_Sales_Model_Order::STATE_PROCESSING,
            Mage::helper('opcheckout')->__('Order was recued by admin id: '.$current_admin_user_id),
            null
        )->save();

        $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);

        $order->setBaseDiscountCanceled(0);
        $order->setBaseShippingCanceled(0);
        $order->setBaseSubtotalCanceled(0);
        $order->setBaseTaxCanceled(0);
        $order->setBaseTotalCanceled(0);
        $order->setDiscountCanceled(0);
        $order->setShippingCanceled(0);
        $order->setSubtotalCanceled(0);
        $order->setTaxCanceled(0);
        $order->setTotalCanceled(0);

        foreach ($order->getAllItems() as $item) {
            $item->setQtyInvoiced($item->getQtyOrdered());
            $item->setQtyCanceled(0);
            $item->setTaxCanceled(0);
            $item->setHiddenTaxCanceled(0);
            $item->save();
        }
        $order->save();

        $order->getPayment()->capture();

        $this->_redirect('adminhtml/sales_order/view', array('order_id' => $order->getId()));
    }
}
