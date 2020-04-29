<?php
class Op_Checkout_ReceiptController extends Mage_Core_Controller_Front_Action
{
    const DISABLE_ACTIVATE_QUOTE_STATUSES = ['closed', 'canceled'];

    /** @var Op_Checkout_Model_Payment */
    protected $checkoutPayment;
    /** @var string */
    protected $orderNo;
    /** @var string */
    protected $status;
    /** @var string */
    protected $signature;

    public function _construct()
    {
        $this->checkoutPayment = Mage::getModel('opcheckout/Payment');
    }

    public function indexAction()
    {
        Mage::log("receipt index", null, 'op_checkout.log', true);
        $this->orderNo = $this->getRequest()->getParam('checkout-reference');
        $this->status = $this->getRequest()->getParam('checkout-status');
        $this->signature = $this->getRequest()->getParam('signature');
        $params = $this->getRequest()->getParams();

        $rcn = "receipt_processing_".$this->orderNo;
        $processingOrderCache = Mage::app()->getCache()->load($rcn);

        if ($processingOrderCache) {
            sleep(1);
            Mage::app()->getCache()->remove($rcn);
        } else {
            Mage::app()->getCache()->save('processing', $rcn);
        }
        try {
            $validate = $this->checkoutPayment->verifyPayment($this->signature, $this->status, $params);
        } catch (Exception $exception) {
            Mage::log($exception->getMessage(), null, 'op_checkout.log');
            $validate = false;
        }

        if (true === $validate) {
            $this->successAction($params);
        } else {
            $this->failureAction();
        }
    }

    public function successAction($params)
    {
        $invoice = false;
        try {
            $invoice = $this->checkoutPayment->validatePayment($params);
        } catch (Exception $exception) {
            Mage::log($exception->getMessage(), null, 'op_checkout.log');
        }

        Mage::log(get_class($invoice), null, 'op_checkout.log', true);

        if ($invoice instanceof Mage_Sales_Model_Order_Invoice || $invoice == "recovery") {
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/onepage/success'))->sendResponse();
        } else {
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/onepage/failure'))->sendResponse();
        }
    }

    public function failureAction()
    {
        Mage::getSingleton('checkout/session')->addError(Mage::helper('opcheckout')->__('Payment failed.'));

        $order = Mage::getModel("sales/order")->loadByIncrementId($this->orderNo);
        $orderStatus = $order->getStatus();
        if ($order->getId() && !in_array($orderStatus, self::DISABLE_ACTIVATE_QUOTE_STATUSES, true)) {
            Mage::getModel('opcheckout/opcheckout')->cancelOrderAndActivateQuote($order);
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/cart'))->sendResponse();
        } else {
            Mage::app()->getResponse()->setRedirect(Mage::getUrl(''))->sendResponse();
        }
        exit;
    }

    public function confirmAction()
    {
        echo 'confirm';
        exit;
    }
}
