<?php
class Op_Checkout_Block_Opcheckout extends Mage_Payment_Block_Form
{
    protected $formId = 'opcheckout_form';
    protected $formAction;

    protected function _construct()
    {
        parent::_construct();
    }

    public function setFormAction($action)
    {
        $this->formAction = $action;
    }

    protected function _toHtml()
    {
        $form = new Varien_Data_Form();

        $form->setAction($this->formAction)
            ->setId($this->formId)
            ->setName($this->formId)
            ->setMethod('POST')
            ->setUseContainer(true);

        foreach ($this->getData() as $field => $value) {
            $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        }

        $html = '<html><body>';
        $html.= $this->__('You will be redirected in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("'.$this->formId.'").submit();</script>';
        $html.= '</body></html>';
        return $html;
    }
}
