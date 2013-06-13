<?php

class Am_Paysystem_Worldpay extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '4.2.17';

    const URL = "https://secure.worldpay.com/wcc/purchase";
    const TEST_URL = "https://secure-test.worldpay.com/wcc/purchase";

    protected $defaultTitle = 'WorldPay';
    protected $defaultDescription = 'purchase using WorldPay';
    
    protected $_canResendPostback = true;
    
    public function getSupportedCurrencies()
    {
        return array(
            'USD', 'EUR', 'GBP',
        );
    }

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addInteger('installation_id', array('size'=>20))
            ->setLabel('WorldPay Installation Id (number)');
//        $form->addText('callback_pw', array('size'=>20))
//            ->setLabel('Callback Password (the same as configured in WorldPay)');
        $form->addAdvCheckbox('testing')->setLabel('Test Mode');
    }
    
    public function isConfigured()
    {
        return $this->getConfig('installation_id') > '';
    }
    
    public function isNotAcceptableForInvoice(Invoice $invoice)
    {
        if ($invoice->rebill_times && ($invoice->first_period != $invoice->second_period))
        {
            return "WorldPay cannot handle products with different first and second period";
        }
        return parent::isNotAcceptableForInvoice($invoice);
    }
    
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $a = new Am_Paysystem_Action_Redirect($this->getConfig('testing') ? self::TEST_URL : self::URL);
        $a->instId = $this->getConfig('installation_id');
        $a->cartId = $invoice->public_id;
        $a->currency = $invoice->currency;
        $a->desc = $invoice->getLineDescription();
        $a->email = $invoice->getEmail();
        $a->name = $invoice->getName();
        $a->address = $invoice->getStreet();
        $a->city = $invoice->getCity();
        $a->state = $invoice->getState();
        $a->postcode = $invoice->getZip();
        //$a->MC_callback = preg_replace('|^https?://|', '', $this->getPluginUrl('ipn'));
        $a->amount = $invoice->first_total;
        if ($this->getConfig('testing'))
        {
            $a->testMode = 100;
            $a->name = 'CAPTURE';
        }
        
        if ($invoice->rebill_times)
        {
            if ($invoice->rebill_times != IProduct::RECURRING_REBILLS)
                $a->noOfPayments = $invoice->rebill_times;
            $a->futurePayType = 'regular';
            if($invoice->rebill_times != 1)
            {
                list($c, $u) = $this->period2Wp($invoice->second_period);
                $a->intervalUnit = $u;
                $a->intervalMult = $c;
            }
            $a->normalAmount = $invoice->second_total;
            $a->option = 0;
            
            list($c, $u) = $this->period2Wp($invoice->first_period);
            $a->startDelayMult = $c;
            $a->startDelayUnit = $u;
        }
        $result->setAction($a);
    }
    
    public function period2Wp($period)
    {
        $p = new Am_Period($period);
        switch ($p->getUnit())
        {
            case Am_Period::DAY:
                return array($p->getCount(), 1);
            case Am_Period::MONTH:
                return array($p->getCount(), 3);
            case Am_Period::YEAR:
                return array($p->getCount(), 4);
            default:
                // nop. exception
        }
        throw new Am_Exception_Paysystem_NotConfigured(
            "Unable to convert period [$period] to Worldpay-compatible.".
            "Must be number of days, months or years");
    }

    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }

    public function getReadme()
    {
return <<<CUT
            Worldpay payment plugin configuration

1. Enable and configure WorldPay Plugin in aMember control panel.
        
        -----------------------------------------

CONFIUGURATION OF WORDPAY ACCOUNT

2. Login into WorldPay Control Panel 
    http://www.worldpay.com/support/bg/index.php?page=newlogin&c=WW
open "Installations", click on "Edit" button and set "Payment Response URL"
    %root_url%/payment/worldpay/ipn
(yes, it will allow to work with several websites with just one account).
You also have to enable the callback, by checking 
the following box: "Payment Response enabled?"
  
You should also enable the printout of the receipt, by checking the box: 
"Enable the Shopper Response".

3. Make test purchase. After your testing is done, disable Worldpay plugin 
Testing mode in aMember Control Panel.

HANDLING OF RECURRING TRANSACTIONS IS NOT YET TESTED.
CUT;
    }
    public function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        try
        {
            $invoiceLog = $this->_logDirectAction($request, $response, $invokeArgs);
            $transaction = $this->createTransaction($request, $response, $invokeArgs);
            if (!$transaction)
            {
                throw new Am_Exception_InputError("Request not handled - createTransaction() returned null");
            }
            $transaction->setInvoiceLog($invoiceLog);
            try {
                $transaction->process();
            } catch (Exception $e) {
                if ($invoiceLog)
                    $invoiceLog->add($e);
                throw $e;
            }
            if ($invoiceLog)
                $invoiceLog->setProcessed();
            //show thanks page without redirect
            if ($transaction->isFirst())
                $this->displayThanks($request, $response, $invokeArgs, $transaction->getInvoice());
        }catch(Exception $e)
        {
            $message = $e->getMessage();
            $view = new Am_View($this->getDi());
            $view->assign('error', $e->getMessage());
            $view->assign('is_html', false);
            $view   ->placeholder("head-start")
                    ->prepend(
                        sprintf(
                            '<base href="%s://%s" />', 
                            (empty($_SERVER['HTTPS']) ? 'http' : 'https'),  
                            Am_Controller::escape($_SERVER['HTTP_HOST']
                                )
                            )
                        );            
            $view->display('error.phtml');
            
        }
        
    }
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Worldpay($this, $request, $response, $invokeArgs);
    }

}

class Am_Paysystem_Transaction_Worldpay extends Am_Paysystem_Transaction_Incoming
{
    protected $isfirst = false;
    public function isFirst()
    {
        return $this->isfirst;
    }
    public function getInvoice()
    {
        return $this->invoice;
    }
    public function findInvoiceId()
    {
        return $this->request->getFiltered('cartId');
    }
    public function getUniqId()
    {
        return $this->request->getFiltered('transId');
    }
    public function getReceiptId()
    {
        return $this->request->getFiltered('transId'); 
    }
    public function validateSource()
    {
        $this->_checkIp(<<<IPS
195.35.90.0-195.35.90.255
155.136.68.0-155.136.68.255
193.41.220.0-193.41.220.255
195.166.19.0-195.166.19.255
193.41.221.0-193.41.221.255
155.136.16.0-155.136.16.255
.outbound.wp3.rbsworldpay.com
.worldpay.com
IPS
        );
        return true;
    }
    public function validateStatus()
    {
        if ($this->request->get('transStatus') == 'C')
            throw new Am_Exception_Paysystem_TransactionInvalid("Transaction Cancelled!");
        
        if ($this->request->get('transStatus') != 'Y')
            throw new Am_Exception_Paysystem_TransactionInvalid("Status is not [Y]");
        if (!$this->getPlugin()->getConfig('testing') && $this->request->get('testMode'))
            throw new Am_Exception_Paysystem_TransactionInvalid("Test Mode Postback while plugin is not in test mode");
        if ($this->getPlugin()->getConfig('installation_id') != $this->request->get('instId'))
            throw new Am_Exception_Paysystem_TransactionInvalid("Foreign transaction - not our instId");
        return true;
    }
    public function validateTerms()
    {   
        if ($this->invoice->status == Invoice::PENDING)
            $this->assertAmount($this->invoice->first_total, $this->getAmount(), 'First Total');
        else
            $this->assertAmount($this->invoice->second_total, $this->getAmount(), 'Second Total');
        return true;
    }
    public function getAmount()
    {
        return $this->request->get('amount');
    }
    public function processValidated()
    {
        if ($this->invoice->status == Invoice::PENDING)
            $this->isfirst = true;
        if ($this->getAmount() > 0)
            $this->invoice->addPayment($this);
        elseif ($this->invoice->status == Invoice::PENDING)
            $this->invoice->addAccessPeriod($this);
    }
}
