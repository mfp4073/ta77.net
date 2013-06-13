<?php

class Am_Paysystem_PaypalExpress extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PAYPAL_EXPRESS_TOKEN = 'paypal-express-token';
    const PAYPAL_EXPRESS_CHECKOUT = "express-checkout";
    const PAYPAL_PROFILE_ID = 'paypal-profile-id';
    
    const SANDBOX_URL = "https://www.sandbox.paypal.com/webscr";
    const LIVE_URL = "https://www.paypal.com/webscr";
    const PLUGIN_REVISION = '4.2.17';
    
    protected $defaultTitle = "PayPal Express";
    protected $defaultDescription = "pay with paypal quickly";
    
    protected $_canResendPostback = true;
    protected $domain;
    
    public function getSupportedCurrencies()
    {
        return array(
            'AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY',
            'MYR', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF',
            'TWD', 'THB', 'USD');
    }

    public function canAutoCreate()
    {
        return false;
    }
    
    public function init(){
        $this->domain = $this->getConfig('testing') ? 'www.sandbox.paypal.com' : 'www.paypal.com';
    }
    public function _initSetupForm(Am_Form_Setup $form)
    {
        Am_Paysystem_PaypalApiRequest::initSetupForm($form);
        
        $form->addText('localecode')->setLabel(array('Locale Code', 'By default: US'));
    }
    
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $log = $this->getDi()->invoiceLogRecord;
        $log->title = "SetExpressCheckout";
        $log->paysys_id = $this->getId();
        $log->setInvoice($invoice);
        $apireq = new Am_Paysystem_PaypalApiRequest($this);
        $apireq->setExpressCheckout($invoice);
        
        $apireq->addPostParameter('LOCALECODE', $this->getConfig('localecode', 'US'));
        
        $log->add($apireq);
        $response = $apireq->send();
        $log->add($response);
        if ($response->getStatus() != 200)
            throw new Am_Exception_Paysystem("Error while communicating to PayPal server, please try another payment processor");
        parse_str($response->getBody(), $vars);
        if (get_magic_quotes_gpc())
            $vars = Am_Request::ss($vars);
        if (empty($vars['TOKEN'])) 
            throw new Am_Exception_Paysystem("Error while communicating to PayPal server, no token received, please try another payment processor");
        $invoice->data()->set(self::PAYPAL_EXPRESS_TOKEN, $vars['TOKEN']);
        $action = new Am_Paysystem_Action_Redirect($this->getConfig('testing') ? self::SANDBOX_URL : self::LIVE_URL);
        $action->cmd = '_express-checkout';
        $action->token = $vars['TOKEN'];
        $log->add($action);
        $result->setAction($action);
        
        $this->getDi()->session->paypal_invoice_id = $invoice->getSecureId('paypal');
        
        // if express-checkout chosen, hide and don't require 
        //      fields for login, password, email, name and address
        // if that is new user,
        //     save user info and invoice into temporary storage not to user table
        // call setExpressCheckout
        // redirect to paypal
        // then get back from paypal to am/payment/paypal-express/review
        // on confirm key pressed, make payment, finish checkout, fill-in fields
        
    }
    public function expressCheckoutAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        $token = $request->getFiltered('token');
        if (!$token) 
            throw new Am_Exception_InputError("No required [token] provided, internal error");
        $log = $this->getDi()->invoiceLogRecord;
        $log->title = "";
        $log->paysys_id = $this->getId();
        if ($request->getInt('do'))
        {
            $invoice = current($this->getDi()->invoiceTable->findByData(self::PAYPAL_EXPRESS_TOKEN, $token));
            if (!$invoice)
                throw new Am_Exception_InternalError("Could not find invoice by token [$token]");
            $this->_setInvoice($invoice);
            $log->setInvoice($invoice);
            
            if ($invoice->first_total > 0)
            {
                // bill initial amount @todo free trial
                $log->title .= " doExpressCheckout";
                $apireq = new Am_Paysystem_PaypalApiRequest($this);
                $apireq->doExpressCheckout($invoice, $token, $request->getFiltered('PayerID'));
                $vars = $apireq->sendRequest($log);
                $transaction = new Am_Paysystem_Transaction_PayPalExpress_DoExpressCheckout($this, $vars);
                $transaction->setInvoice($invoice);
                $transaction->process();
            }
                
            if ($invoice->rebill_times)
            {
                $log->title .= " createRecurringPaymentProfile";
                $apireq = new Am_Paysystem_PaypalApiRequest($this);
                $apireq->createRecurringPaymentProfile($invoice, null, $token, $request->getFiltered('PayerID'));
                $vars = $apireq->sendRequest($log);
                if (!in_array($vars['ACK'], array('Success', 'SuccessWithWarning')))
                {
                    $this->logError("Not Success response to CreateRecurringPaymentProfile request", $vars);
                } else {
                    $invoice->data()->set(self::PAYPAL_PROFILE_ID, $vars['PROFILEID'])->update();
                    if ($invoice->first_total <= 0)
                    {
                        $transaction = new Am_Paysystem_Transaction_PayPalExpress_CreateRecurringPaymentProfile($this, $vars);
                        $transaction->setInvoice($invoice);
                        $transaction->process();
                    }
                }
            }
            return Am_Controller::redirectLocation($this->getReturnUrl());
        } else {
            $log->title .= " getExpressCheckoutDetails";
            $apireq = new Am_Paysystem_PaypalApiRequest($this);
            $apireq->getExpressCheckoutDetails($token);
            $vars = $apireq->sendRequest($log);
            
            $invoiceId = filterId(get_first(
                @$vars['INVNUM'], 
                @$vars['L_PAYMENTREQUEST_0_INVNUM'], 
                // too smart! paypal developers decided to do not pass INVNUM/CUSTOM for transactions with free trial
                $this->getDi()->session->paypal_invoice_id)
            );
            if (!$invoiceId || !($invoice = $this->getDi()->invoiceTable->findBySecureId($invoiceId, 'paypal')))
                throw new Am_Exception_InputError("Could not find invoice related to given payment. Internal error. Your account was not billed, please try again");
            $log->setInvoice($invoice);
            $log->update();
            $this->_setInvoice($invoice);
            /* @var $invoice Invoice */
            if ($invoice->isPaid()) 
            {
                return Am_Controller::redirectLocation($this->getReturnUrl());
            }
            $invoice->data()->set(self::PAYPAL_EXPRESS_TOKEN, $token)->update();
            $view = new Am_View;
            $view->invoice = $invoice;
            $view->url = $this->getPluginUrl(self::PAYPAL_EXPRESS_CHECKOUT);
            $view->hidden = array(
                'do' => '1',
                'token' => $request->getFiltered('token'),
                'PayerID' => $request->getFiltered('PayerID'),
            );
            $view->display("payment-confirm.phtml");
        }
    }
    
    public function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        if ($request->getActionName() == self::PAYPAL_EXPRESS_CHECKOUT)
            return $this->expressCheckoutAction($request, $response, $invokeArgs);
        else
            return parent::directAction($request, $response, $invokeArgs);
    }
    
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Paypal($this, $request, $response, $invokeArgs);
    }
    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }
//    public function hideBricks()
//    {
//        return array('email', 'name', 'address');
//    }
    function getUserCancelUrl(Invoice $invoice)
    {
        return 'https://' . $this->domain . '?' . http_build_query(array(
            'cmd' => 'subscr-find',
            'alias' => $this->getConfig('business')
        ));
    }
    public function getAdminCancelUrl(Invoice $invoice)
    {
        return 'https://' . $this->domain . '?' . http_build_query(array(
            'cmd' => '_subscr-find',
            'alias' => $this->getConfig('business')
        ));
    }
    
    public function getReadme()
    {
        $url = $this->getPluginUrl('ipn');
return <<<CUT
You have to enable IPN in your Paypal account and set up IPN URL to :
  <b><i>$url</i></b>
CUT;
    }
    
}

class Am_Paysystem_Transaction_PayPalExpress_DoExpressCheckout extends Am_Paysystem_Transaction_Abstract
{
    protected $vars;
    public function __construct(Am_Paysystem_Abstract $plugin, array $vars)
    {
        $this->vars = $vars;
        parent::__construct($plugin);
    }
    public function getUniqId()
    {
        return $this->vars['PAYMENTINFO_0_TRANSACTIONID'];
    }
    public function validate()
    {
        if (!in_array($this->vars['ACK'], array('Success', 'SuccessWithWarning')))
        {
            throw new Am_Exception_Paysystem_TransactionInvalid("Error: " . $this->vars['L_SHORTMESSAGE0']);
        }
        if (!empty($this->vars['PAYMENTREQUEST_0_SHORTMESSAGE']))
            throw new Am_Exception_Paysystem_TransactionInvalid("Payment failed: " . $this->vars['PAYMENTREQUEST_0_SHORTMESSAGE']);
        if (!in_array($this->vars['PAYMENTINFO_0_PAYMENTSTATUS'], array('Completed', 'Processed')))
        {
            throw new Am_Exception_Paysystem_TransactionInvalid("Transaction status is not ok: [" . $this->vars['PAYMENTINFO_0_PAYMENTSTATUS'] . "]");
        }
        return true;
          
    }
    public function getAmount()
    {
        return $this->vars['PAYMENTINFO_0_AMT'];
    }
    public function findTime()
    {
        $d = new DateTime($this->vars['PAYMENTINFO_0_ORDERTIME']);
        $d->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $d;
    }
}

class Am_Paysystem_Transaction_PayPalExpress_CreateRecurringPaymentProfile extends Am_Paysystem_Transaction_Abstract
{
    protected $vars;
    public function __construct(Am_Paysystem_Abstract $plugin, array $vars)
    {
        $this->vars = $vars;
        parent::__construct($plugin);
    }
    public function getUniqId()
    {
        return $this->vars['PROFILEID'] . '-' . $this->vars['CORRELATIONID'];
    }
    public function validate()
    {
        if ($this->vars['ACK'] != 'Success')
        {
            throw new Am_Exception_Paysystem_TransactionInvalid("Error: " . $this->vars['L_SHORTMESSAGE0']);
        }
    }
    public function getAmount()
    {
        return 0;
    }
    public function findTime()
    {
        $d = new DateTime($this->vars['TIMESTAMP']);
        $d->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $d;
    }
    public function processValidated()
    {
        $this->invoice->addAccessPeriod($this);
    }
}

