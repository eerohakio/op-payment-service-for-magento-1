<?php
class Op_Checkout_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('opcheckout/form.phtml');
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
}
