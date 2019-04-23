<?php

class Op_Checkout_Block_Payment_Info extends Mage_Payment_Block_Info
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('opcheckout/info.phtml');
    }

    public function getOpCheckoutLogo()
    {
        return Mage::getStoreConfig('payment/opcheckout/logo');
    }


}