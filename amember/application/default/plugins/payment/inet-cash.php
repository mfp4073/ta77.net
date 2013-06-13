<?php

class Am_Paysystem_InetCash extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.2.17';
    
    protected $defaultTitle = 'Inet-Cash';
    protected $defaultDescription = '...much more than online payment solutions';
    
    const URL = 'https://www.inet-cash.com/mc/shop/start/';
    
    public function getSupportedCurrencies()
    {
        return array('EUR', 'USD');
    }
    
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {        
        $shopid = $request->get('shopid');    
        $invoice = array_shift(Am_Di::getInstance()->invoiceTable->findByPublicId($shopid));
        
        $params = array();
        $params['nachname'] = $invoice->getLastName();
        $params['vorname'] = $invoice->getFirstName();
        $params['strasse'] = $invoice->getStreet();
        $params['plz'] = $invoice->getZip();
        $params['ort'] = $invoice->getCity();
        $params['land'] = '';//$invoice->getCoutry();
        $params['email'] = $invoice->getEmail();
        $params['betrag'] = $invoice->first_total * 100;
        $params['compain_id'] = '';
        $params['ipadresse'] = $invoice->getUser()->remote_addr;
        
        //params for recurring
        $params['aboanlage'] = $invoice->second_period ? '1' : '';
        $params['abopreis'] = $invoice->second_period ? $invoice->second_total : '';
        $params['abozeit'] = $invoice->second_period ? $invoice->second_total : '';
        $params['abonext'] = $invoice->second_period ? $invoice->first_period : '';
        $params['cur'] = strtolower($invoice->currency);
        
        foreach($params as $p)
        {
            $message .= $p.";";
        }        
        
        $response->setBody(substr($message, 0, -1)); 
        
        return new Am_Paysystem_Transaction_Inet_Cash($this, $request, $response, $invokeArgs);
    }
    
    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }
    
    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('siteid', array('size' => 20, 'maxlength' => 20))
            ->setLabel("Site-ID: will be assigned by INET-CASH after you create your shop");
        
        //$form->addText('shopid', array('size' => 20, 'maxlength' => 20))
        //    ->setLabel("Your unique transaction number");
        
        $form->addSelect('lang', array(), array('options' =>
            array(
                'en' => 'English',
                'de' => 'Deutsch',
                'es' => 'Español',
                'pl' => 'język polski',
                'fr' => 'français'        
        )))->setLabel("Language");
        
        $form->addSelect('zahlart', array(), array('options' =>
            array(
                'cc' => 'Credit Card',
                'dd' => 'Direct Debit, only Germany/Austria',
                'db' => 'Sofortuberweisung',
                'dp' => 'Payment in advance'       
        )))->setLabel("Payment method");
        
        $form->addText('owntxt', array('size' => '18', 'maxlength' => 18))
            ->setLabel("Your own text will be shown on the top of the payment form.");
        
        $form->addAdvCheckbox('tatype')->setLabel("If you want to make an autorization, you have to check");
        
        $form->addAdvCheckbox('test')->setLabel("Test Mode Enabled");
    }
    
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {  
        $params = array();
        //$params['siteid'] = $this->getConfig('siteid'); 
        $params['shopid'] = $invoice->public_id;       
        $params['lang'] = $this->getConfig('lang');
        $params['zahlart'] = $this->getConfig('zahlart');
        $params['owntxt'] = $this->getConfig('owntxt');
        //$params['tatype'] = $this->getConfig('tatype') ? 'author' : '';
        
        $url = self::URL;
        $url.= $this->getConfig('siteid')."?".http_build_query($params);
       
        $a = new Am_Paysystem_Action_Form($url); 
        $result->setAction($a);
    }  
    
    function getReadme()
    {
        return <<<CUT
<b>Inet-Cash Configuration</b>
CUT;
    }      
}

class Am_Paysystem_Transaction_Inet_Cash extends Am_Paysystem_Transaction_Incoming
{
    public function __construct(Am_Paysystem_Abstract $plugin, Am_Request $request, Zend_Controller_Response_Http $response, $invokeArgs)
    {   
        $shopid = $request->get('shopid');    
        $invoice = array_shift(Am_Di::getInstance()->invoiceTable->findByPublicId($shopid));
        
        $params = array();
        $params['nachname'] = $invoice->getLastName();
        $params['vorname'] = $invoice->getFirstName();
        $params['strasse'] = $invoice->getStreet();
        $params['plz'] = $invoice->getZip();
        $params['ort'] = $invoice->getCity();
        $params['land'] = '';//$invoice->getCoutry();
        $params['email'] = $invoice->getEmail();
        $params['betrag'] = $invoice->first_total * 100;
        $params['compain_id'] = '';
        $params['ipadresse'] = $invoice->getUser()->remote_addr;
        
        //params for recurring
        $params['aboanlage'] = $invoice->second_period ? '1' : '';
        $params['abopreis'] = $invoice->second_period ? $invoice->second_total : '';
        $params['abozeit'] = $invoice->second_period ? $invoice->second_total : '';
        $params['abonext'] = $invoice->second_period ? $invoice->first_period : '';
        $params['cur'] = strtolower($invoice->currency);  

        foreach($params as $p)
        {
            $message .= $p.";";
        }
        $response->setBody(substr($message, 0, -1));
        
        $uri = $request->getRequestUri();
        $response->setRedirect($request->getRequestUri());
        
        echo $message;
    
        parent::__construct($plugin, $request, $response, $invokeArgs);
    }
    
    public function findInvoiceId()
    {
        return $this->request->get('shopid');
    }

    public function getUniqId()
    {
        return $this->request->get('tranceno');
    }

    public function validateSource()
    {
        $shopid = $this->request->get('shopid');
        
        $invoice = Am_Di::getInstance()->invoiceTable->findByPublicId($shopid);
        
        if(is_null($invoice))
            return false;
        return true;            
    }

    public function validateStatus()
    {
        $art = $this->request->get('art');
        
        if($art == 'request')
        {  
            $params = array();
            $params['nachname'] = $this->invoice->getLastName();
            $params['vorname'] = $this->invoice->getFirstName();
            $params['strasse'] = $this->invoice->getStreet();
            $params['plz'] = $this->invoice->getZip();
            $params['ort'] = $this->invoice->getCity();
            $params['land'] = strtolower($this->invoice->getCountry());
            $params['email'] = $this->invoice->getEmail();
            $params['betrag'] = $this->invoice->first_total * 100;   
            $params['compain_id'] = '';
            $params['ipadresse'] = $this->invoice->getUser()->remote_addr; 
            
            //params for recurringforeach($params as $p)          
            $this->response->setBody(substr($message, 0, -1));
            $params['aboanlage'] = $this->invoice->second_period ? '1' : '';
            $params['abopreis'] = $this->invoice->second_period ? $this->invoice->second_total : '';
            $params['abozeit'] = $this->invoice->second_period ? $this->invoice->second_total : '';
            $params['abonext'] = $this->invoice->second_period ? $this->invoice->first_period : '';
            $params['cur'] = strtolower($this->invoice->currency);  

            foreach($params as $p)
            {
                $message .= $p.";";
            }
            $this->response->setBody(substr($message, 0, -1));
            
            echo substr($message, 0, -1);            
  
            return false;
        }
        elseif($art == 'result')
        {
            
        }
        else
            return false;
            
        return true;
    }

    public function validateTerms()
    {
        return true;
    }
}