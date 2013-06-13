<?php
class Am_Paysystem_Sagepay extends Am_Paysystem_CreditCard{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '4.2.17';

    protected $defaultTitle = 'Sagepay';
    protected $defaultDescription = 'Pay by credit card';
    
    /*const TEST_URL = "https://test.sagepay.com/simulator/VSPServerGateway.asp?Service=VendorRegisterTx";
    const LIVE_URL = "https://live.sagepay.com/simulator/VSPServerGateway.asp?Service=VendorRegisterTx";*/
    
    const TEST_URL = "https://test.sagepay.com/gateway/service/vspserver-register.vsp";
    const LIVE_URL = "https://live.sagepay.com/gateway/service/vspserver-register.vsp";
    
    const REPEAT_TEST_URL = "https://live.sagepay.com/gateway/service/repeat.vsp";
    const REPEAT_LIVE_URL = "https://live.sagepay.com/gateway/service/repeat.vsp";
    
    function storesCcInfo(){
        return false;
    }
    
    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('login')->setLabel(array('Your SagePay login', ''));
        //$form->addPassword('pass')->setLabel(array('Your SagePay password', ''));
        $form->addAdvCheckbox('testing')->setLabel("Test Mode Enabled");        
    }

    public function init()
    {
        parent::init();
    }
    public function getSupportedCurrencies()
    {
        return array('AUD', 'CAD', 'CHF', 'DKK', 'EUR', 'GBP', 
            'HKD', 'IDR', 'JPY', 'LUF', 'NOK', 'NZD', 'SEK', 'SGD', 'TRL', 'USD');
    }
    
    function getReadme(){
        return <<<CUT
You need to add IP of your server to list "Valid IP Addresses" in your Sagepay merchant account.
    
Amember signup form has to be configured to ask for address info.
CUT;
        
    }
    public function _doBill(Invoice $invoice, $doFirst, CcRecord $cc=null, Am_Paysystem_Result $result)
    {
        $transaction = new Am_Paysystem_Transaction_Sagepay_Rebill($this, $invoice, null, $doFirst);
        $transaction->run($result);
    }

    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $u = $invoice->getUser();
        $request = $this->createHttpRequest();
        $vars = array(
            'VPSProtocol'  => '2.23',
            'TxType'    => 'PAYMENT',
            'Vendor' => $this->getConfig('login'),
            'VendorTxCode' => $invoice->public_id . '-AMEMBER',
            'Amount' => number_format($invoice->first_total, 2, '.', ''),
            'Currency' => $invoice->currency ? $invoice->currency : 'USD',
            'Description' =>   $invoice->getLineDescription(),
            'NotificationURL' => $this->getPluginUrl('ipn'),
            'SuccessURL'    =>  $this->getReturnUrl(),
            'RedirectionURL'    =>  $this->getReturnUrl(),

            'BillingFirstnames' => $u->name_f,
            'BillingSurname' => $u->name_l,
            'BillingAddress1' => $u->street,
            'BillingCity' => $u->city,
            'BillingPostCode' => $u->zip,
            'BillingCountry' => $u->country,

            'DeliveryFirstnames' => $u->name_f,
            'DeliverySurname' => $u->name_l,
            'DeliveryAddress1' => $u->street,
            'DeliveryCity' => $u->city,
            'DeliveryPostCode' => $u->zip,
            'DeliveryCountry' => $u->country,

            'CustomerEMail' => $u->email,
            'Profile' => 'NORMAL',            
        );
        if($u->country == 'US'){
            $vars['BillingState'] = $u->state;
            $vars['DeliveryState'] = $u->state;
        }
        $request->addPostParameter($vars);
        $request->setUrl($this->getConfig('testing') ? self::TEST_URL : self::LIVE_URL);
        $request->setMethod(Am_HttpRequest::METHOD_POST);
        $this->logRequest($request);
        $response = $request->send();
        $this->logResponse($response);
        if(!$response->getBody()) 
            throw new Am_Exception_InputError("An error occured while payment request");
        $res = array();
        foreach(split(PHP_EOL, $response->getBody()) as $line){
        	list($l,$r) = explode('=',$line,2);
        	$res[trim($l)]=trim($r);
        }
        if($res['Status']=='OK'){
            $invoice->data()->set('sagepay_securitykey', $res['SecurityKey']);
            $invoice->update();
            $a = new Am_Paysystem_Action_Form($res['NextURL']);
            $result->setAction($a);
        }
        else
            throw new Am_Exception_InputError($res['StatusDetail']);
    }
    
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_SagePay($this, $request, $response, $invokeArgs);
    }

    public function getRecurringType()
    {
        return self::REPORTS_CRONREBILL;
    }
    
}

class Am_Paysystem_Transaction_Sagepay extends Am_Paysystem_Transaction_Incoming
{

    public function getAmount()
    {
        return moneyRound($this->request->get('ctransamount'));
    }
    
    public function getUniqId()
    {
        return $this->request->get("VPSTxId");
    }
    
    public function findInvoiceId(){
        list($id,$t_) = split('-',$this->request->get("VendorTxCode"));
        return $id;
    }

    public function validateSource()
    {
        $this->invoice = $this->getInvoice();
        $hash = strtoupper(md5($this->request->get("VPSTxId").
            $this->request->get("VendorTxCode").
            $this->request->get("Status").
            $this->request->get("TxAuthNo").
            $this->getPlugin()->getConfig('login').
            $this->request->get("AVSCV2").
            $this->invoice->data()->get("sagepay_securitykey").
            $this->request->get("AddressResult").
            $this->request->get("PostCodeResult").
            $this->request->get("CV2Result").
            $this->request->get("GiftAid").
            $this->request->get("3DSecureStatus").
            $this->request->get("CAVV").
            $this->request->get("AddressStatus").
            $this->request->get("PayerStatus").
            $this->request->get("CardType").
            $this->request->get("Last4Digits")));
        return $hash == $this->request->get("VPSSignature");
    }
    
    public function validateStatus()
    {
        if($this->request->get('Status') != 'OK'){
        $this->getPlugin()->_setInvoice($this->getInvoice());
            echo "Status=OK
RedirectURL=".$this->getPlugin()->getCancelUrl()."
StatusDetail=Redirect";
            die;
        }
        else
            return true;
    }
    public function validateTerms()
    {
        return true;
    }
    public function processValidated()
    {
        $this->invoice->data()->set('sagepay_vpstxid',$this->request->get('VPSTxId'))
            ->set('sagepay_vendortxcode',$this->request->get('VendorTxCode'))
            ->set('sagepay_txauthno',$this->request->get('TxAuthNo'));
        $this->invoice->update();
        $this->invoice->addpayment($this);
        $this->getPlugin()->_setInvoice($this->getPlugin()->getDi()->invoiceTable->findFirstByPublicId($this->findInvoiceId()));
        echo "Status=OK
RedirectURL=".$this->getPlugin()->getReturnUrl()."
StatusDetail=Redirect";        
    }
    function getInvoice(){
        return $this->loadInvoice($this->findInvoiceId());
    }    
}

class Am_Paysystem_Transaction_Sagepay_Rebill extends Am_Paysystem_Transaction_CreditCard
{
    
    protected $ret;
    public function run(Am_Paysystem_Result $result)
    {
        $request = $this->createHttpRequest();
        $vars = array(
            'VPSProtocol'  => '2.23',
            'TxType'    => 'REPEAT',
            'Vendor' => $this->getPlugin()->getConfig('login'),
            'VendorTxCode' => $invoice->public_id . 'AMEMBER',
            'Amount' => number_format($invoice->second_total, 2, '.', ''),
            'Currency' => $invoice->currency ? $invoice->currency : 'USD',
            'Description' =>   $invoice->getLineDescription(),
            'RelatedVPSTxId' =>   $this->invoice->data()->get('sagepay_vpstxid'),
            'RelatedVendorTxCode' => $this->invoice->data()->get('sagepay_vendortxcode'),
            'RelatedSecurityKey' => $this->invoice->data()->get('sagepay_securitykey'),
            'RelatedTxAuthNo' => $this->invoice->data()->get('sagepay_txauthno')
        );
        $request->addPostParameter($vars);
        $request->setUrl($this->getConfig('testing') ? self::REPEAT_TEST_URL : self::REPEAT_LIVE_URL);
        $request->setMethod(Am_HttpRequest::METHOD_POST);
        $this->logRequest($request);
        $response = $request->send();
        $this->logResponse($response);
        
        if(!$response->getBody()){
            $result->setFailed(___("Payment failed"). ":" . ___("Empty response from Sagepay server"));
        }else{
            $res = array();
            foreach(split(PHP_EOL, $response->getBody()) as $line){
                list($l,$r) = explode('=',$line,2);
                $res[trim($l)]=trim($r);
            }            
            if($res['Status']=='OK'){
                $this->ret = $res;
                $result->setSuccess($this);
                $this->processValidated();
            }
            else
                $result->setFailed(___("Payment failed"). ":" . $res['StatusDetail']);
        }
    }
    
    
    
    public function getUniqId()
    {
        return $this->ret['VPSTxId'];
    }
    
    public function parseResponse()
    {
        
    }
    public function validate() 
    {
        $this->result->setSuccess($this);
    }
    public function processValidated()
    {
        $this->invoice->addPayment($this);
    }    
}
