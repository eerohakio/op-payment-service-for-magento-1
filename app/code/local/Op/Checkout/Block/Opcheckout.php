<?php
class Op_Checkout_Block_Opcheckout extends Mage_Payment_Block_Form
{
    protected $formId = 'opcheckout_form';
    protected $formAction;

    protected function _construct()
    {
        parent::_construct();
        //$this->setTemplate('opcheckout/form.phtml');
    }

    public function setFormAction($action)
    {
        $this->formAction = $action;
    }

    protected function _toHtml()
    {
        //var_dump($this->formAction); exit;

        $form = new Varien_Data_Form();

        $form->setAction($this->formAction)
            ->setId($this->formId)
            ->setName($this->formId)
            ->setMethod('POST')
            ->setUseContainer(true);

        foreach ($this->getData() as $field=>$value) {
            $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        }

      //  $form->addField('asd', 'submit', ['name'=>'submit', 'value'=>'submit']);
       // var_dump($form); exit;

        $html = '<html><body>';
        $html.= $this->__('You will be redirected in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("'.$this->formId.'").submit();</script>';
        $html.= '</body></html>';
        return $html;
    }


}
