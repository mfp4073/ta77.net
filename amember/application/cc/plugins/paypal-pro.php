<?php
/**
 * @todo do not save free trial $1 as real payment
 * 
 */

class Am_Paysystem_PaypalPro extends Am_Paysystem_CreditCard
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    /** key in invoice data */
    const PAYPAL_PROFILE_ID = 'paypal-profile-id';
    
    protected $defaultTitle = "PayPal Pro";
    protected $defaultDescription = "accepts Visa, MasterCard";
    
    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }

    public function getFormOptions()
    {
        $ret = parent::getFormOptions();
        $ret[] = self::CC_PHONE;
        return $ret;
    }

    public function storesCcInfo()
    {
        return false;
    }
    
    public function getSupportedCurrencies()
    {
        return array(
            'AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY',
            'MYR', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF',
            'TWD', 'THB', 'USD');
    }
    /**
     * For UK, only Maestro, Solo, MasterCard, Discover, and Visa are 
     * allowable. For Canada, only MasterCard and Visa are allowable; Interac debit cards are not supported
     * NOTE: If the credit card type is Maestro or Solo, the currencyId must be GBP. 
     * In addition, either StartMonth and StartYear or IssueNumber must be specified.
     */
    public function getCreditCardTypeOptions()
    {
        return array(
            'Visa' => 'Visa', 
            'MasterCard' => 'MasterCard',
            'Discover' => 'Discover',
            'Amex' => 'Amex',
//            'Maestro' => 'Maestro',
//            'Solo' => 'Solo',
        );
    }
    public function _doBill(Invoice $invoice, $doFirst, CcRecord $cc, Am_Paysystem_Result $result)
    {
        if(!$doFirst) return; // Recurring payments should not be handled by cron. 
        
        if (!$invoice->rebill_times)
        {
            $request = new Am_Paysystem_PaypalApiRequest($this);
            $request->doSale($invoice, $cc);
        } else {
            $request = new Am_Paysystem_PaypalApiRequest($this);
            $request->createRecurringPaymentProfile($invoice, $cc);
        }
        $tr = new Am_Paysystem_Transaction_PaypalPro($this, $invoice, $request, $doFirst);
        $tr->run($result); // send payment request and check response
    }
    public function _initSetupForm(Am_Form_Setup $form)
    {
        Am_Paysystem_PaypalApiRequest::initSetupForm($form);
        $form->addAdvCheckbox('send_shipping')->setLabel("Send user's address as shipping address to PayPal");
    }
    public function onSetupForms(Am_Event_SetupForms $event)
    {
        parent::onSetupForms($event);
        $event->getForm('paypal-pro')->removeElementByName('payment.'.$this->getId().'.reattempt');
    }
    
    
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Paypal($this, $request, $response, $invokeArgs);
    }
    
    public function cancelInvoice(Invoice $invoice)
    {
        $log = Am_Di::getInstance()->invoiceLogRecord;
        $log->title = "cancelRecurringPaymentProfile";
        $log->paysys_id = $this->getId();
        
        $apireq = new Am_Paysystem_PaypalApiRequest($this);
        $apireq->cancelRecurringPaymentProfile($invoice);
        $result = $apireq->sendRequest($log);
        $log->setInvoice($invoice);
        $log->update();
        if($result['ACK'] != 'Success')
            throw new Am_Exception_InputError('Transaction was not cancelled. Got error from paypal: '.$result['L_SHORTMESSAGE0']);
        
    }
    

    public function ccRebill($date = null) {
        
        /* Disable cron rebill process 
         * Rebills will be handled through IPN.
         */
    }
    public function getReadme()
    {
        $url = $this->getPluginUrl('ipn');
return <<<CUT
<b>PayPal PRO payment plugin installation</b>

Up to date instructions how to enable and configure PayPal PRO plugin you  may find at 
<a href='http://www.amember.com/docs/Payment/PaypalPro'>http://www.amember.com/docs/Payment/PaypalPro</a>

<b>IMPORTANT:</b> You <b>MUST</b> set IPN url in your Paypal Profile to: 
        
  <b><i>$url</i></b>

CUT;
        
    }
}

class Am_Paysystem_Transaction_PaypalPro extends Am_Paysystem_Transaction_CreditCard
{
    
    public function validate()
    {
        if (empty($this->vars['ACK']))
            return $this->result->setFailed(___("Payment failed"));
        if (!in_array($this->vars['ACK'], array('Success', 'SuccessWithWarning')))
            return $this->result->setFailed(___("Payment failed") . " : " . $this->vars['ACK']);
        if (!empty($this->vars['L_SHORTMESSAGE0']))
            return $this->result->setFailed(___("Payment failed") . " : " . $this->vars['L_SHORTMESSAGE0']);
        $this->result->setSuccess();
    }
    public function getUniqId()
    {
        return @$this->vars['TRANSACTIONID'];
    }
    public function parseResponse()
    {
        parse_str($this->response->getBody(), $this->vars);
        if (get_magic_quotes_gpc())
            $this->vars = Am_Request::ss($this->vars);
    }
    public function processValidated()
    {
        if ($this->invoice->first_total > 0)
            $this->invoice->addPayment($this); 
        else
            $this->invoice->addAccessPeriod($this); // start free trial
        if (!empty($this->vars['PROFILEID']))
            $this->invoice->data()->set(Am_Paysystem_PaypalPro::PAYPAL_PROFILE_ID, $this->vars['PROFILEID'])->update();
    }
}