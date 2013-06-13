<?php
/**
 * @todo Implement cancellations when someone will help with testing. 
 * 
 */
class Am_Paysystem_Zombaio extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '4.2.17';

    protected $defaultTitle = 'Zombaio';
    protected $defaultDescription = 'Pay by credit card/debit card';
    const API_CANCEL = "Cancel"; 
    const API_REFUND = "Refund"; 

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addInteger('site_id', array('size' => 20))
            ->setLabel('Your Zombaio Site ID');
        $form->addInteger('merchant_id', array('size' => 20))
            ->setLabel(array('Your Zombaio Merchant ID', 'Can be found in ZOA dashboard'));
        
        $form->addText('password')->setLabel(array("Zombaio GW Pass", "Unique key for the verify site/merchant. Can be found under site information in Zombaio Online Administrator"));
        $form->addSelect("lang", array(), array('options' => array(
                'ZOM' => 'Default (Script will detect user language based on IP)',
                'US' => 'English',
                'FR' => 'French',
                'DE' => 'German',
                'IT' => 'Italian',
                'JP' => 'Japanese',
                'ES' => 'Spanish',
                'SE' => 'Swedish',
                'KR' => 'Korean',
                'CH' => 'Traditional Chinese',
                'HK' => 'Simplified Chinese'
            )))->setLabel('Zombaio Site Language');
    }

    public function isConfigured()
    {
        return $this->getConfig('site_id') > '';
    }

    public function getActionURL(Invoice $invoice)
    {
        return sprintf("https://secure.zombaio.com/?%d.%d.%s", 
                            $this->getConfig('site_id'), 
                            $invoice->getItem(0)->getBillingPlanData('zombaio_pricing_id'), 
                            $this->getConfig('lang', 'ZOM')
            );
    }

    public function getAPIURL($type, $params){
        return sprintf("https://secure.zombaio.com/API/%s/?%s", $type, http_build_query($params));
    }
    
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $action = new Am_Paysystem_Action_Form($this->getActionURL($invoice));
        $action->FirstName = $invoice->getFirstName();
        $action->LastName = $invoice->getLastName();
        $action->Address = $invoice->getStreet();
        $action->Postal = $invoice->getZip();
        $action->City = $invoice->getCity();
        $action->Email = $invoice->getEmail();
        $action->Username = $invoice->getLogin();
        $action->extra = $invoice->public_id;
        $action->return_url_approve = $this->getReturnUrl($request);
        $action->return_url_decline = $this->getCancelUrl($request);
        $action->return_url_error = $this->getCancelUrl($request);
        $result->setAction($action);
    }
    
    public function getSupportedCurrencies()
    {
        return array('USD', 'EUR', 'CAD', 'JPY');
    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Zombaio($this, $request, $response, $invokeArgs);
    }

    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }

    public function init()
    {
        parent::init();
        $this->getDi()->billingPlanTable->customFields()
            ->add(new Am_CustomFieldText('zombaio_pricing_id', "Zombaio Pricing ID",
                    "you must create the same product<br />
             in Zombaio and enter its number here"));
    }
    function getReadme()
    {
        $rootURL = $this->getDi()->config->get('root_url');

        return <<<CUT
<b>Zombaio Payment Plugin Configuration</b>
1. Create equivalents for all aMember products in Your Zombaio account.
   Make sure it has the same subscription terms (period, price) as aMember
   Products.
2. Configure aMember CP -> Products -> Manage Products -> Edit -> Zombaio Pricing ID
   You can get Pricing ID from Signup form URL created by ZOMBAIO. 
   In your Zombaio account go to Tools -> Pricing Structure -> Manage 
   You will see  Join Form URL: 
        https://secure.zombaio.com/?287653706.1379928.ZOM
   In this url 287653706 - your Site ID 
               1379928   - Pricing ID
        
3. Set Postback URL (ZScript) for your site at Zombaio merchant account to
   {$rootURL}/payment/zombaio/ipn
CUT;
    }

    function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs){
        try
        {
            return parent::directAction($request, $response, $invokeArgs);
        }
        catch (Exception $e)
        {
            $this->getDi()->errorLogTable->log($e);
            print "ERROR";
            exit;
        }
    }
    
    
    function processRefund(InvoicePayment $payment, Am_Paysystem_Result $result, $amount)
    {
        list(, $trans_id) = split("-", $payment->receipt_id);
        try {
            $r = new Am_HttpRequest($this->getAPIURL(self::API_REFUND, 
                        array(
                            'TRANSACTION_ID'    =>  $trans_id,
                            'MERCHANT_ID'       =>  $this->getConfig("merchant_id"),
                            'ZombaioGWPass'     =>  $this->getConfig("password"),
                            'Refund_Type'       =>  1
                        )));
            $response = $r->send();
        } catch (Exception $e) {
            $this->getDi()->errorLogTable->logException($e);
        }
        if($response && $response->getBody() == 1){
            $trans = new Am_Paysystem_Transaction_Manual($this);
            $trans->setAmount($amount);
            $trans->setReceiptId($payment->receipt_id.'-zombaio-refund');
            $result->setSuccess($trans);
        }else{
            $result->setFailed(array('Error Processing Refund!'));
        }
    }
    
}

class Am_Paysystem_Transaction_Zombaio extends Am_Paysystem_Transaction_Incoming
{
    const ACTION_ADD = 'user.add';
    const ACTION_REBILL = 'rebill';
    const ACTION_DELETE = 'user.delete';
    function getSiteID(){
        return $this->request->get("SiteID") ? $this->request->get("SiteID") : $this->request->get("SITE_ID");
    }
    function getSubscriptionID(){
        return $this->request->get("SubscriptionID") ? $this->request->get("SubscriptionID") : $this->request->get("SUBSCRIPTION_ID");
    }
    
    public function findBySubscriptionId(){
        return $this->getPlugin()->getDi()->db->selectCell("
            SELECT i.public_id
            FROM ?_invoice_payment p LEFT JOIN ?_invoice i 
            USING(invoice_id)
            WHERE p.paysys_id = 'zombaio' AND p.transaction_id LIKE ?
            LIMIT 1
            ", $this->getSubscriptionID().'-%');
    }
    
    public function findInvoiceId(){
        return ($this->request->get("extra") ? $this->request->get("extra") : $this->findBySubscriptionId());
    }
    public function getUniqId()
    {
        return sprintf("%s-%s", $this->getSubscriptionID(), $this->request->get("TRANSACTION_ID"));
    }

    public function validateSource()
    {
        if($this->request->get("ZombaioGWPass") != $this->plugin->getConfig("password"))
            throw new Am_Exception_Paysystem_TransactionInvalid("Incorrect GW Password submited!");
        if(($this->request->get('Action') == self::ACTION_ADD) && ($this->getSiteID() != $this->plugin->getConfig("site_id")))
            throw new Am_Exception_Paysystem_TransactionInvalid("Transaction submited for another site!");
        return true;
    }

    public function validateStatus()
    {
        if($this->request->get('Action') == self::ACTION_REBILL)
            return (intval($this->request->get('Success')) == 1);
        
        return true;
    }

    public function validateTerms()
    {
        if(($this->request->get('Action') == self::ACTION_ADD) && 
            ($this->request->get("PRICING_ID") != $this->invoice->getItem(0)->getBillingPlanData('zombaio_pricing_id')))
        {
            throw new Am_Exception_Paysystem_TransactionInvalid("Wrong PRICING ID used");
        }
        return true;
    }
    
    public function processValidated(){
        switch($this->request->get('Action')){
            case self::ACTION_ADD : 
            case self::ACTION_REBILL : 
                $this->invoice->addPayment($this);
                break;
            case self::ACTION_DELETE : 
               $this->invoice->stopAccess($this);
                break;
        }
        print "OK";
        
    }
}