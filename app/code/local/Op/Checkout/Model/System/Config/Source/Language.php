<?php
class Op_Checkout_Model_System_Config_Source_Language
{

    public function toOptionArray()
    {
        return array(
            array('value' => 'fi_FI', 'label' => Mage::helper("opcheckout")->__('Finnish')),
            array('value' => 'sv_SE', 'label' => Mage::helper("opcheckout")->__('Swedish')),
            array('value' => 'en_US', 'label' => Mage::helper("opcheckout")->__('English')),
        );
    }
}