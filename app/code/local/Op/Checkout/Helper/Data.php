<?php
class Op_Checkout_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function skipBankSelection()
    {
        return Mage::getStoreConfig('payment/opcheckout/skipbankselection');
    }

    public function notifyCanceledOrder($order)
    {

        $template_id  = 'canceled_order_payment_verification';
        $email_template = Mage::getModel('core/email_template')->loadDefault($template_id);
        $email_template_variables = ["increment_id" => $order->getIncrementId()];

        $defaultTo = Mage::getStoreConfig('trans_email/ident_general/email');

        $storeId = Mage::app()->getStore()->getId();

        $customTo = Mage::getStoreConfig('payment/opcheckout/notifications');
        $sender_name = Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_STORE_STORE_NAME).
            " - " . Mage::getStoreConfig('trans_email/ident_general/name');

        $email_template->setSenderName($sender_name);
        $email_template->setSenderEmail($defaultTo);

        if (isset($customTo)) {
            $emails = explode(',', trim($customTo));
            foreach ($emails as $email) {
                $recipient = trim($email);
                echo "sending to ".$email."\n";
                $email_template->send($recipient, $sender_name, $email_template_variables, $storeId);
            }
        } else {
            $email_template->send($defaultTo, $sender_name, $email_template_variables, $storeId);
        }

        return true;

    }

}
