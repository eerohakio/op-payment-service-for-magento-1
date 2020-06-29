<?php

class Op_Checkout_Block_Payment_Info extends Mage_Payment_Block_Info
{
    const PAYMENT_METHOD_MAPPING = [
      'osuuspankki1' => 'Osuuspankki',
      'nordea2' => 'Nordea',
      'handelsbanken3' => 'Handelsbanken',
      'pop4' => 'POP Pankki',
      'aktia5' => 'Aktia',
      'saastopankki6' => 'Säästöpankki',
      'omasp7' => 'OmaSP',
      'spankki8' => 'S-Pankki',
      'alandsbanken9' => 'Ålandsbanken',
      'danske10' => 'Danske Bank',
      'creditcard1' => 'Visa',
      'creditcard2' => 'Visa Electron',
      'creditcard3' => 'MasterCard',
    ];
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('opcheckout/info.phtml');
    }

    public function getOpCheckoutLogo()
    {
        return Mage::getStoreConfig('payment/opcheckout/logo');
    }

    /**
     * @return string
     */
    public function getPaymentMethod()
    {
        $method = $this->getInfo()->getAdditionalInformation('opcheckout_payment_method');
        return self::PAYMENT_METHOD_MAPPING[$method] ?? $this->__('Online payment');
    }
}
