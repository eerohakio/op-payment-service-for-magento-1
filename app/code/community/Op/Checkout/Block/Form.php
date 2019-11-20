<?php
class Op_Checkout_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('opcheckout/form.phtml')->setMethodLabelAfterHtml($this->getImage());

    }

    protected function getImageUrl()
    {
        $logo = Mage::getStoreConfig('payment/opcheckout/logo');
        return $logo;
    }

    protected function getAvailableMethods()
    {
        $checkout_api  = Mage::getModel('opcheckout_api/checkout');
        return $checkout_api->getEnabledPaymentMethodGroups();
    }

    protected function getSkipBankSelection()
    {
        return Mage::helper('opcheckout')->skipBankSelection();
    }

    protected function getImage()
    {
        $html = "<img src='";
        $html .= $this->getImageUrl() . "'";
        $html .= " style='width:22px; float:right; '";
        return $html;
    }

}
