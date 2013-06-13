<?php

class Am_Paysystem_Offline extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '4.2.17';
    
    public function __construct(Am_Di $di, array $config)
    {
        $this->defaultTitle = ___("Offline Payment");
        $this->defaultDescription = ___("pay using wire transfer or by sending offline check");
        parent::__construct($di, $config);
    }
    
    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addTextarea("html", array("cols"=>80, "rows"=>10))->setLabel(
                ___("Payment Instructions for customer\n". 
                "you can enter any HTML here, it will be displayed to\n".
                "customer when he chooses to pay using this payment system\n".
                "you can use the following tags:\n".
                "%s - Invoice Title\n".
                "%s - Invoice Id\n".
                "%s - Invoice Total", '%invoice_title%', '%invoice.public_id%', '%invoice.first_total%'));
    }
    
    public function getSupportedCurrencies()
    {
        return array_keys(Am_Currency::getFullList()); // support any
    }

    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $result->setAction(
            new Am_Paysystem_Action_Redirect(
                REL_ROOT_URL . "/payment/".$this->getId()."/instructions?id=".$invoice->getSecureId($this->getId())));
    }
    public function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        $invoice = $this->getDi()->invoiceTable->findBySecureId($request->getFiltered('id'), $this->getId());
        if (!$invoice)
            throw new Am_Exception_InputError(___("Sorry, seems you have used wrong link"));
        $view = new Am_View;
        $html = $this->getConfig('html', 'SITE OWNER DID NOT PROVIDE INSTRUCTIONS FOR OFFLINE PAYMENT YET');
        
        $tpl = new Am_SimpleTemplate;
        $tpl->invoice = $invoice;
        $tpl->user = $this->getDi()->userTable->load($invoice->user_id);
        $tpl->invoice_id = $invoice->invoice_id;
        $tpl->cancel_url = REL_ROOT_URL . '/cancel?id=' . $invoice->getSecureId('CANCEL');
        $tpl->invoice_title = $invoice->getLineDescription();
        
        $view->content = $tpl->render($html);
        $view->title = $this->getTitle();
        $response->setBody($view->render("layout.phtml"));
    }
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
    }
    public function getRecurringType()
    {
        return self::REPORTS_NOT_RECURRING;
    }
}