<?php

class Am_Paysystem_Stripe extends Am_Paysystem_CreditCard
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_DATE = '$Date$';
    const PLUGIN_REVISION = '4.2.17';
    
    const TOKEN = 'stripe_token';
    const CC_EXPIRES = 'stripe_cc_expires';
    const CC_MASKED = 'stripe_cc_masked';
    
    protected $_pciDssNotRequired = true;
    
    public function getRecurringType()
    {
        return self::REPORTS_CRONREBILL;
    }
    public function getSupportedCurrencies()
    {
        return array('USD', 'CAD');
    }
    
    public function _doBill(Invoice $invoice, $doFirst, CcRecord $cc, Am_Paysystem_Result $result)
    {
        $token = $invoice->getUser()->data()->get(self::TOKEN);
        if (!$token)
            return $result->setErrorMessages(array(___('Payment failed')));
        if ($doFirst && (doubleval($invoice->first_total) <= 0))
        { // free trial
            $tr = new Am_Paysystem_Transaction_Free($this);
            $tr->setInvoice($invoice);
            $tr->process();
            $result->setSuccess($tr);
        } else {
            $tr = new Am_Paysystem_Transaction_Stripe($this, $invoice, $doFirst);
            $tr->run($result);
        }
    }
    public function getUpdateCcLink($user)
    {
        if ($user->data()->get(self::TOKEN))
            return $this->getPluginUrl('update');
    }
    public function storeCreditCard(CcRecord $cc, Am_Paysystem_Result $result)
    {
    }
    public function loadCreditCard(Invoice $invoice)
    {
        if ($invoice->getUser()->data()->get(self::TOKEN))
            return $this->getDi()->CcRecordTable->createRecord(); // return fake record for rebill
    }
    protected function createController(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Controller_CreditCard_Stripe($request, $response, $invokeArgs);
    }
    protected function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('secret_key', 'size=40')->setLabel('Secret Key')->addRule('required');
        $form->addText('public_key', 'size=40')->setLabel('Publishable Key')->addRule('required');
    }
    public function processRefund(InvoicePayment $payment, Am_Paysystem_Result $result, $amount)
    {
        $tr = new Am_Paysystem_Transaction_Stripe_Refund($this, $payment->getInvoice(), $payment->receipt_id, $amount);
        $tr->run($result);
    }
}

class Am_Controller_CreditCard_Stripe extends Am_Controller
{
    /** @var Invoice*/
    protected $invoice;
    /** @var Am_Paysystem_Stripe */
    protected $plugin;
    
    public function setInvoice(Invoice $invoice) { $this->invoice = $invoice; }
    public function setPlugin($plugin) { $this->plugin = $plugin; }
    
    public function createForm($label, $cc_mask = null)
    {
        $form = new Am_Form('cc-stripe');
        
        $name = $form->addGroup()->setLabel(array(___('Cardholder Name'), sprintf(___('cardholder first and last name, exactly as%son the card'), '<br/>')));
        $name->addRule('required', ___('Please enter credit card holder name'));
        $name_f = $name->addText('cc_name_f', array('size'=>15, 'id' => 'cc_name_f'));
        $name_f->addRule('required', ___('Please enter credit card holder first name'))->addRule('regex', ___('Please enter credit card holder first name'), '|^[a-zA-Z_\' -]+$|');
        $name_l = $name->addText('cc_name_l', array('size'=>15, 'id' => 'cc_name_l'));
        $name_l->addRule('required', ___('Please enter credit card holder last name'))->addRule('regex', ___('Please enter credit card holder last name'), '|^[a-zA-Z_\' -]+$|');

        $cc = $form->addText('', array('autocomplete'=>'off', 'size'=>22, 'maxlength'=>22, 'id' => 'cc_number'))
                ->setLabel(___('Credit Card Number'), ___('for example: 1111-2222-3333-4444'));
        if ($cc_mask)
            $cc->setAttribute('placeholder', $cc_mask);
        $cc->addRule('required', ___('Please enter Credit Card Number'))
            ->addRule('regex', ___('Invalid Credit Card Number'), '/^[0-9 -]+$/');

        class_exists('Am_Form_CreditCard', true); // preload element
        $expire = $form->addElement(new Am_Form_Element_CreditCardExpire('cc_expire'))
            ->setLabel(array(___('Card Expire'), ___('Select card expiration date - month and year')));
        
        $code = $form->addPassword('', array('autocomplete'=>'off', 'size'=>4, 'maxlength'=>4, 'id' => 'cc_code'))
                ->setLabel(___('Credit Card Code'), sprintf(___('The "Card Code" is a three- or four-digit security code%sthat is printed on the back of credit cards in the card\'s%ssignature panel (or on the front for American Express cards).'),'<br>','<br>'));
        $code->addRule('required', ___('Please enter Credit Card Code'))
             ->addRule('regex', ___('Please enter Credit Card Code'), '/^\s*\d{3,4}\s*$/');
            
        $fieldSet = $form->addFieldset(___('Address Info'))->setLabel(array(___('Address Info'), ___('(must match your credit card statement delivery address)')));
        $street = $fieldSet->addText('cc_street')->setLabel(___('Street Address'))
                           ->addRule('required', ___('Please enter Street Address'));
        
        $zip = $fieldSet->addText('cc_zip')->setLabel(___('ZIP'))
                        ->addRule('required', ___('Please enter ZIP code'));
        
        $form->addSubmit('', array('value' => $label));
        
        $form->addHidden('id')->setValue($this->_request->get('id'));
        $form->addHidden('stripe_info', 'id=stripe_info')->addRule('required');
        
        $key = json_encode($this->plugin->getConfig('public_key'));
        $form->addScript()->setScript(file_get_contents(dirname(__FILE__) . '/../../default/views/public/js/json2.min.js'));
        $form->addScript()->setScript(<<<CUT
jQuery(function($){
    function am_make_base_auth(user, password) {
        return "Basic " + btoa(user + ':' + password);
    }
    $("form#cc-stripe").submit(function(event){
        var frm = $(this);
        if (frm.find("input[name=stripe_info]").val() > '')
            return true; // submit the form!
        event.stopPropagation();
        Stripe.setPublishableKey($key);
        Stripe.createToken({
            number: frm.find("#cc_number").val(),
            cvc: frm.find("#cc_code").val(),
            exp_month: frm.find("[name='cc_expire[m]']").val(),
            exp_year: frm.find("[name='cc_expire[y]']").val(),
            name: frm.find("#cc_name_f").val() + " " + frm.find("#cc_name_l").val(),
            address_zip : frm.find("[name=cc_zip]").val(),
            address_line1 : frm.find("[name=cc_street]").val()
        }, function(status, response){ // handle response
            if (status == '200')
            {
                frm.find("input[name=stripe_info]").val(JSON.stringify(response));
                frm.submit();
            } else {
                frm.find("input[type=submit]").prop('disabled', null);
                var msg;
                if (response.error.type == 'card_error')
                    msg = response.error.message;
                else
                    msg = 'Payment failure, please try again later';
                var el = frm.find("#cc_number");
                var cnt = el.closest(".element");
                cnt.addClass("error");
                cnt.find("span.error").remove();
                el.after("<span class='error'><br />"+msg+"</span>");
            }
        });
        frm.find("input[type=submit]").prop('disabled', 'disabled');
        return false;
    });
});   
CUT
        );
        
        return $form;
    }
    
    public function updateAction()
    {
        $user = $this->getDi()->user;
        $token = $user->data()->get(Am_Paysystem_Stripe::TOKEN);
        if (!$token)
            throw new Am_Exception_Paysystem("No credit card stored, nothing to update");
        $this->invoice = $this->getDi()->invoiceTable->findFirstBy(
            array('user_id'=>$user->pk(), 'paysys_id'=>$this->plugin->getId()), 'invoice_id DESC');
        if (!$this->invoice)
            throw new Am_Exception_Paysystem("No invoices found for user and paysystem");
        $tr = new Am_Paysystem_Transaction_Stripe_GetCustomer($this->plugin, $this->invoice, $token);
        $tr->run(new Am_Paysystem_Result());
        $info = $tr->getInfo();
        if (empty($info['id'])) // cannot load profile
        { // todo delete old profile, and display cc form again!
            throw new Am_Exception_Paysystem("Could not load customer profile");
        }
        
        $this->form = $this->createForm(___('Update Credit Card Info'), 'XXXX XXXX XXXX ' . $info['active_card']['last4']);
        $n = preg_split('/\s+/', $info['active_card']['name'], 2);
        $this->form->addDataSource(new HTML_QuickForm2_DataSource_Array(array(
            'cc_street' => $info['active_card']['address_line1'],
            'cc_name_f' => $n[0], 
            'cc_name_l' => $n[1],
            'cc_zip' => $info['active_card']['address_zip'],
            'cc_expire' => sprintf('%02d%02d', 
                    $info['active_card']['exp_month'],
                    $info['active_card']['exp_year']-2000),
        )));
        $result = $this->ccFormAndSaveCustomer();
        
        if ($result->isSuccess())
            $this->_redirect(ROOT_SURL . '/member');
        
        $this->form->getElementById('stripe_info')->setValue('');
        $this->view->headScript()->appendFile('https://js.stripe.com/v1/');
        $this->view->title = ___('Payment info');
        $this->view->display_receipt = false;
        $this->view->form = $this->form;
        $this->view->display('cc/info.phtml');
    }
    
    protected function ccFormAndSaveCustomer()
    {
        $vars = $this->form->getValue();
        $result = new Am_Paysystem_Result();
        if (!empty($vars['stripe_info']))
        {
            $stripe_info = json_decode($vars['stripe_info'], true);
            if (!$stripe_info['id'])
                throw new Am_Exception_Paysystem("No expected token id received");
            $tr = new Am_Paysystem_Transaction_Stripe_CreateCustomer($this->plugin, $this->invoice, $stripe_info['id']);
            $tr->run($result);
            if ($result->isSuccess())
            {
                $this->invoice->getUser()->data()
                    ->set(Am_Paysystem_Stripe::TOKEN, $tr->getUniqId())
                    ->set(Am_Paysystem_Stripe::CC_EXPIRES, sprintf('%02d%02d',
                        $stripe_info['card']['exp_month'], $stripe_info['card']['exp_year']-2000))
                    ->set(Am_Paysystem_Stripe::CC_MASKED, 
                        'XXXX' . $stripe_info['card']['last4'])
                    ->update();
                // setup session to do not reask payment info within 30 minutes
                $s = new Zend_Session_Namespace($this->plugin->getId());
                $s->setExpirationSeconds(60*30); // after 30 minutes we will reset the session
                $s->ccConfirmed = true;
            } else {
                $this->view->error = $result->getErrorMessages();
            }
        }
        return $result;
    }
    
    protected function displayReuse()
    {
        $result = new Am_Paysystem_Result;
        $tr = new Am_Paysystem_Transaction_Stripe_GetCustomer($this->plugin, $this->invoice, 
                $this->invoice->getUser()->data()->get(Am_Paysystem_Stripe::TOKEN));
        $tr->run($result);
        if (!$result->isSuccess())
            throw new Am_Exception_Paysystem("Stored customer profile not found");
        
        $card = $tr->getInfo();
        $card = 'XXXX XXXX XXXX ' . $card['active_card']['last4'];
        
        $text = ___('Click "Continue" to pay this order using stored credit card %s', $card);
        $continue = ___('Continue');
        $cancel = ___('Cancel');
        
        $action = $this->plugin->getPluginUrl('cc');
        $id = Am_Controller::escape($this->_request->get('id'));
        $action = Am_Controller::escape($action);;
        $this->view->content .= <<<CUT
<div class='am-reuse-card-confirmation'>
$text
<form method='get' action='$action'>
    <input type='hidden' name='id' value='$id' />
    <input type='submit' class='tb-btn tb-btn-primary' name='reuse_ok' value='$continue' />
    &nbsp;&nbsp;&nbsp;
    <input type='submit' class='tb-btn' name='reuse_cancel' value='$cancel' />
</form>
</div>
   
CUT;
        $this->view->display('layout.phtml');
    }
    
    public function ccAction()
    {
        $this->view->title = ___('Payment info');
        $this->view->display_receipt = true;
        $this->view->invoice = $this->invoice;
        // if we have credit card on file, we will try to use it but we
        // have to display confirmation first
        if ($this->invoice->getUser()->data()->get(Am_Paysystem_Stripe::TOKEN))
        {
            $s = new Zend_Session_Namespace($this->plugin->getId());
            $s->setExpirationSeconds(60*30); // after 30 minutes we will reset the session
            if ($this->_request->get('reuse_ok') || (@$s->ccConfirmed === true))
            {
                $result = $this->plugin->doBill($this->invoice, true, $this->getDi()->CcRecordTable->createRecord());
                if ($result->isSuccess())
                {
                    return $this->_redirect($this->plugin->getReturnUrl());
                } else {
                    $this->invoice->getUser()->data()
                        ->set(Am_Paysystem_Stripe::TOKEN, null)
                        ->set(Am_Paysystem_Stripe::CC_EXPIRES, null)
                        ->set(Am_Paysystem_Stripe::CC_MASKED, null)
                        ->update();
                    $this->view->error = $result->getErrorMessages();
                    $s->ccConfirmed = false; // failed
                }
            } elseif ($this->_request->get('reuse_cancel') || (@$s->ccConfirmed === false)) {
                $s->ccConfirmed = false;
            } elseif (!isset($s->ccConfirmed)) {
                return $this->displayReuse();
            }
        }
        
        $this->form = $this->createForm(___('Subscribe And Pay'));
        $result = $this->ccFormAndSaveCustomer();
        if ($result->isSuccess())
        {
            $result = $this->plugin->doBill($this->invoice, true, $this->getDi()->CcRecordTable->createRecord());
            if ($result->isSuccess())
            {
                return $this->_redirect($this->plugin->getReturnUrl());
            } else {
                $this->invoice->getUser()->data()
                    ->set(Am_Paysystem_Stripe::TOKEN, null)
                    ->set(Am_Paysystem_Stripe::CC_EXPIRES, null)
                    ->set(Am_Paysystem_Stripe::CC_MASKED, null)
                    ->update();
                $this->view->error = $result->getErrorMessages();
            }
        }
        $this->form->getElementById('stripe_info')->setValue('');
        $this->view->headScript()->appendFile('https://js.stripe.com/v1/');
        $this->view->form = $this->form;
        $this->view->display('cc/info.phtml');
    }
}

class Am_Paysystem_Transaction_Stripe extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = array();
    
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst)
    {
        $request = new Am_HttpRequest('https://api.stripe.com/v1/charges', 'POST');
        $amount = $doFirst ? $invoice->first_total : $invoice->second_total;
        $request->setAuth($plugin->getConfig('secret_key'), '')
            ->addPostParameter('amount', (int)(sprintf('%.02f', $amount)*100))
            ->addPostParameter('currency', $invoice->currency)
            ->addPostParameter('customer', $invoice->getUser()->data()->get(Am_Paysystem_Stripe::TOKEN))
            ->addPostParameter('description', 'Invoice #'.$invoice->public_id.': '.$invoice->getLineDescription());
        parent::__construct($plugin, $invoice, $request, $doFirst);
    }
    public function getUniqId()
    {
        return $this->parsedResponse['id'];
    }
    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }
    public function validate()
    {
        if (@$this->parsedResponse['paid'] != 'true')
        {
            if ($this->parsedResponse['error']['type'] == 'card_error')
                $this->result->setErrorMessages(array($this->parsedResponse['error']['message']));
            else
                $this->result->setErrorMessages(array(___('Payment failed')));
            return false;
        }
        $this->result->setSuccess($this);
        return true;
    }
}

/**
 * Convert temporary credit card profile to customer record
 */
class Am_Paysystem_Transaction_Stripe_CreateCustomer extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = array();
    
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $token)
    {
        $request = new Am_HttpRequest('https://api.stripe.com/v1/customers', 'POST');
        $request->setAuth($plugin->getConfig('secret_key'), '')
            ->addPostParameter('card', $token)
            ->addPostParameter('email', $invoice->getEmail())
            ->addPostParameter('description', 'Username:' . $invoice->getUser()->login);
        parent::__construct($plugin, $invoice, $request, true);
    }
    public function getUniqId()
    {
        return $this->parsedResponse['id'];
    }
    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }
    public function validate()
    {
        if (!@$this->parsedResponse['id'])
        {
            $this->result->setErrorMessages(array('Error storing customer profile'));
            return false;
        }
        $this->result->setSuccess($this);
        return true;
    }
    public function processValidated()
    {
    }
}


class Am_Paysystem_Transaction_Stripe_GetCustomer extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = array();
    
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $token)
    {
        $request = new Am_HttpRequest('https://api.stripe.com/v1/customers/' . $token, 'GET');
        $request->setAuth($plugin->getConfig('secret_key'), '');
        parent::__construct($plugin, $invoice, $request, true);
    }
    public function getUniqId()
    {
        return $this->parsedResponse['id'];
    }
    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }
    public function validate()
    {
        if (!@$this->parsedResponse['id'])
            $this->result->setErrorMessages(array('Unable to fetch payment profile'));
        $this->result->setSuccess($this);
    }
    public function getInfo()
    {
        return $this->parsedResponse;
    }
    public function processValidated()
    {
        
    }
}

class Am_Paysystem_Transaction_Stripe_Refund extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = array();
    protected $charge_id;
    protected $amount;
    
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $charge_id, $amount = null)
    {
        $this->charge_id = $charge_id;
        $this->amount = $amount > 0 ? $amount : null;
        $request = new Am_HttpRequest('https://api.stripe.com/v1/charges/' . $this->charge_id . '/refund', 'POST');
        $request->setAuth($plugin->getConfig('secret_key'), '');
        if ($this->amount > 0)
            $request->addPostParameter('amount', sprintf('%.2f', $this->amount)*100);
        parent::__construct($plugin, $invoice, $request, true);
    }
    public function getUniqId()
    {
        return $this->parsedResponse['id'] . '-refund';
    }
    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }
    public function validate()
    {
        if (!@$this->parsedResponse['id'])
            $this->result->setErrorMessages(array('Unable to fetch payment profile'));
        $this->result->setSuccess($this);
    }
    public function processValidated()
    {
        $this->invoice->addRefund($this, $this->charge_id, $this->amount);
    }
}