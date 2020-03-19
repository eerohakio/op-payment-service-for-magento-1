<?php

class Op_Checkout_Block_Payment_Info extends Mage_Payment_Block_Info
{
    const METHOD_TITLE = 'Op Payment Service';

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('opcheckout/info.phtml');
    }

    public function getOpCheckoutLogo()
    {
        return Mage::getStoreConfig('payment/opcheckout/logo');
    }

    public function getPaymentServiceTitle()
    {
        return self::METHOD_TITLE;
    }
}