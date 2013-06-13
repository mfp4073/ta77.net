<?php

class Am_Paysystem_Payflow extends Am_Paysystem_CreditCard
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_DATE = '$Date$';
    const PLUGIN_REVISION = '4.2.17';    
    
    const LIVE_URL = 'https://payflowpro.paypal.com';
    const TEST_URL = 'https://pilot-payflowpro.paypal.com';
    
    const USER_PROFILE_KEY = 'payflow-reference-transaction';
    
    protected $defaultTitle = "Pay with your Credit Card";
    protected $defaultDescription  = "accepts all major credit cards";

    public function getRecurringType()
    {
        return self::REPORTS_CRONREBILL;
    }
    public function isConfigured()
    {
        return $this->getConfig('user') && $this->getConfig('pass');
    }
    public function getSupportedCurrencies()
    {
        return array('USD', 'CAD', 'GBP');
    }
    public function _doBill(Invoice $invoice, $doFirst, CcRecord $cc, Am_Paysystem_Result $result)
    {
        $addCc = true;
        if ($doFirst) // it is a first charge, cc info present
        {
            if (!(float)$invoice->first_total) // not recurring sale
            {
                $tr = new Am_Paysystem_Payflow_Transaction_Authorization($this, $invoice, $doFirst, $cc);
            } else {
                $tr = new Am_Paysystem_Payflow_Transaction_Sale($this, $invoice, $doFirst, $cc);
            }
        } else {
            $user = $invoice->getUser();
            $profileId = $user->data()->get(self::USER_PROFILE_KEY);
            if (!$profileId)
                return $result->setFailed(array("No saved reference transaction for customer"));
            $tr = new Am_Paysystem_Payflow_Transaction_Sale($this, $invoice, $doFirst, null, $profileId);
        }
        $tr->run($result);
    }
    
    public function storeCreditCard(CcRecord $cc, Am_Paysystem_Result $result)
    {
        $user = $this->getDi()->userTable->load($cc->user_id);
        $profileId = $user->data()->get(self::USER_PROFILE_KEY);
        
        if ($this->invoice)
        { // to link log records with current invoice
            $invoice = $this->invoice;
        } else { // updating credit card info?
            $invoice = $this->getDi()->invoiceRecord;
            $invoice->invoice_id = 0;
            $invoice->user_id = $user->pk();
        }

        // compare stored cc for that user may be we don't need to refresh?
        if ($profileId && ($cc->cc_number != '0000000000000000'))
        {
            $storedCc = $this->getDi()->ccRecordTable->findFirstByUserId($user->pk());
            if ($storedCc && (($storedCc->cc != $cc->maskCc($cc->cc_number)) || ($storedCc->cc_expire != $cc->cc_expire)))
            {
                $user->data()
                    ->set(self::USER_PROFILE_KEY, null)
                    ->update();
                $profileId = null;
            }
        }
        
        if (!$profileId)
        {
            try {
                $tr = new Am_Paysystem_Payflow_Transaction_Upload($this, $invoice, $cc);
                $tr->run($result);
                if (!$result->isSuccess())
                    return;
                $user->data()->set(Am_Paysystem_Payflow::USER_PROFILE_KEY, $tr->getProfileId())->update();
            } catch (Am_Exception_Paysystem $e) {
                $result->setFailed($e->getPublicError());
                return false;
            }
        }
        
        /// 
        $cc->cc = $cc->maskCc(@$cc->cc_number);
        $cc->cc_number = '0000000000000000';
        if ($cc->pk())
            $cc->update();
        else
            $cc->replace();
        $result->setSuccess();
    }
    
    protected function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('vendor')->setLabel('Merchant Vendor Id (main username)');
        $form->addText('user')->setLabel('Merchant User Id (of the API user, or the same as Vendor Id)');
        $form->addPassword('pass')->setLabel('Merchant Password');
        $form->addText('partner')->setLabel('Partner');
        $form->setDefault('partner', 'PayPal');
        $form->addAdvCheckbox('testing')->setLabel('Test Mode');
    }
    
    public function processRefund(InvoicePayment $payment, Am_Paysystem_Result $result, $amount)
    {
        $tr = new Am_Paysystem_Payflow_Transaction_Refund($this, $payment->getInvoice(), $payment->receipt_id, $amount);
        $tr->run($result);
    }
}

class Am_Paysystem_Payflow_Transaction extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = array();
    
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst)
    {
        $request = new Am_HttpRequest($plugin->getConfig('testing') ? Am_Paysystem_Payflow::TEST_URL : Am_Paysystem_Payflow::LIVE_URL, 
            Am_HttpRequest::METHOD_POST);

        parent::__construct($plugin, $invoice, $request, $doFirst);
        
        $this->addRequestParams();
    }
    
    public function run(Am_Paysystem_Result $result)
    {
        $reqId = sha1(serialize($this->request->getPostParams())); // unique id of request
        
        $this->request->setHeader('X-VPS-REQUEST-ID', $reqId);
        $this->request->setHeader('X-VPS-CLIENT-TIMEOUT', 60);
        $this->request->setHeader('X-VPS-VIT-INTEGRATION-PRODUCT', 'aMember Pro');
       // $this->request->setHeader('Content-Type', 'text/namevalue');
        
        $this->request->addPostParameter('VERBOSITY', 'HIGH');
        
        return parent::run($result);
    }
    
    protected function addRequestParams()
    {
        $this->request->addPostParameter('VENDOR', $this->plugin->getConfig('vendor'));
        $this->request->addPostParameter('USER', $this->plugin->getConfig('user'));
        $this->request->addPostParameter('PWD', $this->plugin->getConfig('pass'));
        $this->request->addPostParameter('PARTNER', $this->plugin->getConfig('partner'));
    }
    
    public function getUniqId()
    {
        return strlen(@$this->parsedResponse->PPREF) ? $this->parsedResponse->PPREF : $this->parsedResponse->PNREF;
    }
    public function getReceiptId()
    {
        return $this->parsedResponse->PNREF;
    }
    public function getAmount()
    {
        return $this->doFirst ? $this->invoice->first_total : $this->invoice->second_total;
    }
    public function parseResponse()
    {
        parse_str($this->response->getBody(), $this->parsedResponse);
        $this->parsedResponse = (object)$this->parsedResponse;
        if (!strlen(@$this->parsedResponse->RESULT))
            $this->parsedResponse->RESULT = -1; // wrong response received
    }
    
    public function validate()
    {
        if ($this->parsedResponse->RESULT != '0')
            return $this->result->setFailed(array("Transaction declined, please check credit card information"));
        $this->result->setSuccess($this);
    }
    function setCcRecord(CcRecord $cc)
    {
        $this->request->addPostParameter(array(
            'ACCT' => $cc->cc_number,
            'EXPDATE' => $cc->cc_expire,
            'BILLTOFIRSTNAME' => $cc->cc_name_f,
            'BILLTOLASTNAME' => $cc->cc_name_l,
            'BILLTOSTREET' => $cc->cc_street,
            'BILLTOCITY' => $cc->cc_city,
            'BILLTOSTATE' => $cc->cc_state,
            'BILLTOZIP' => $cc->cc_zip,
            'BILLTOCOUNTRY' => $cc->cc_country,
            'CVV2' => $cc->getCvv(),
        ));
    }
}

class Am_Paysystem_Payflow_Transaction_Upload extends Am_Paysystem_Payflow_Transaction
{
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, CcRecord $cc)
    {
        parent::__construct($plugin, $invoice, true);
        $this->setCcRecord($cc);
    }
    protected function addRequestParams()
    {
        parent::addRequestParams();
        
        $this->request->addPostParameter(array(
            'TRXTYPE'  => 'L',
            'TENDER'   => 'C',  
            'COMMENT'  => 'UPLOAD: ' . $this->invoice->getLineDescription(),
            'CUSTIP'   => $this->doFirst ? $_SERVER['REMOTE_ADDR'] : $this->invoice->getUser()->get('remote_addr'),
        ));
    }
    public function getProfileId()
    {
        return $this->parsedResponse->PNREF;
    }
    public function processValidated()
    {
    }
}

class Am_Paysystem_Payflow_Transaction_Sale extends Am_Paysystem_Payflow_Transaction
{
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst, CcRecord $cc = null, $referenceId = null)
    { 
        parent::__construct($plugin, $invoice, $doFirst, $referenceId);
        if ($cc)
            $this->setCcRecord($cc);
        elseif ($referenceId)
            $this->request->addPostParameter('ORIGID', $referenceId);
    }
    protected function addRequestParams()
    {
        parent::addRequestParams();
        
        $this->request->addPostParameter(array(
            'TRXTYPE'  => 'S',
            'TENDER'   => 'C',  
            'AMT'      => $this->doFirst ? $this->invoice->first_total : $this->invoice->second_total,
            'CURRENCY' => $this->invoice->currency,
            'COMMENT'  => $this->invoice->getLineDescription(),
            'CUSTIP'   => $this->doFirst ? $_SERVER['REMOTE_ADDR'] : $this->invoice->getUser()->get('remote_addr'),
            'INVNUM'   => $this->invoice->public_id . '-' . substr(md5(rand()),1,6),
        ));
        if (!$this->doFirst)
            $this->request->addPostParameter ('RECURRING', 'Y');
            
    }
}

class Am_Paysystem_Payflow_Transaction_Authorization extends Am_Paysystem_Payflow_Transaction
{
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst, CcRecord $cc)
    {
        parent::__construct($plugin, $invoice, $doFirst);
        $this->setCcRecord($cc);
    }
    
    protected function addRequestParams()
    {
        parent::addRequestParams();
        
        $this->request->addPostParameter(array(
            'TRXTYPE'  => 'A',
            'TENDER'   => 'C',  
            'AMT'      => 0,
            'COMMENT'  => $this->invoice->getLineDescription(),
            'CUSTIP'   => $this->doFirst ? $_SERVER['REMOTE_ADDR'] : $this->invoice->getUser()->get('remote_addr'),
            'INVNUM'   => $this->invoice->public_id,
        ));
    }
    
    public function processValidated()
    {
    }
}

class Am_Paysystem_Payflow_Transaction_Refund extends Am_Paysystem_Payflow_Transaction
{
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $origId, $amount)
    {
        parent::__construct($plugin, $invoice, true);
        
        $this->request->addPostParameter('ORIGID', $origId);
        $this->amount = $amount;
        $this->request->addPostParameter('AMT', $amount);
    }
    public function getAmount()
    {
        return $this->amount;
    }
    protected function addRequestParams()
    {
        parent::addRequestParams();
        
        $this->request->addPostParameter(array(
            'TRXTYPE'  => 'C',
            'TENDER'   => 'C',  
        ));
        
    }
    public function processValidated()
    {
        $this->invoice->addRefund($this);
    }
}
