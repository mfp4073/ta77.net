<?php

class Am_Newsletter_Plugin_Mailchimp extends Am_Newsletter_Plugin
{
    function _initSetupForm(Am_Form_Setup $form)
    {
        $el = $form->addPassword('api_key', array('size' => 40))->setLabel('MailChimp API Key'.
            "\n<a target='_blank' href=''></a>");
        $el->addRule('required');
        $el->addRule('regex', 'API Key must be in form xxxxxxxxxxxx-xxx', '/^[a-zA-Z0-9]+-[a-zA-Z0-9]{2,4}$/');
        
        $form->addAdvCheckbox('disable_double_optin')->setLabel(
            array(
                'Disable Double Opt-in', 
                '<a href="http://kb.mailchimp.com/article/how-does-confirmed-optin-or-double-optin-work">http://kb.mailchimp.com/article/how-does-confirmed-optin-or-double-optin-work</a>'
                ));
        $form->addAdvCheckbox('send_welcome')->setLabel(
            array(
                'Send Welcome Message', 
                'Should Mailchimp send Welcome Email after opt-in'
                ));
        
    }
    /** @return Am_Plugin_Mailchimp */
    function getApi()
    {
        return new Am_Mailchimp_Api($this);
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        $api = $this->getApi();
        foreach ($addLists as $list_id)
        {
            $ret = $api->sendRequest('listSubscribe', array(
                'id' => $list_id,
                'email_address' => $user->email,
                'double_optin' => $this->getConfig('disable_double_optin') ? false : true,
                'update_existing' => true,
                //'replace_interests' => '', 
                'send_welcome' => ($this->getConfig('send_welcome')? 1 : 0),
                'merge_vars' => array(
                    'FNAME' => $user->name_f,
                    'LNAME' => $user->name_l,
                    'LOGIN' => $user->login,
                ),
            ));
            if (!$ret) return false;
        }
        foreach ($deleteLists as $list_id)
        {
            $ret = $api->sendRequest('listUnsubscribe', array(
                'id' => $list_id,
                'email_address' => $user->email,
                'delete_member' => 0,
                'send_goodbye'  => 1,
                'send_notify'   => 0,
            ));
            if (!$ret) return false;
        }
        return true;
    }

    public function getLists()
    {
        $api = $this->getApi();
        $ret = array();
        $start = 0;
        do
        {
            $lists = $api->sendRequest('lists', array('start'=>$start++));
            foreach ($lists['data'] as $l)
                $ret[$l['id']] = array(
                    'title' => $l['name'],
                );
        } while(@count($lists['data'])>0);
        return $ret;
    }
    
    public function getReadme()
    {
        return <<<CUT
   MailChimp plugin readme
       
This module allows aMember Pro users to subscribe/unsubscribe from e-mail lists
created in MailChimp. To configure the module:

 - go to <a target='_blank' href='https://us4.admin.mailchimp.com/account/api/'>www.mailchimp.com -> Account -> API Keys and Authorized Apps</a>
 - if no "API Keys" exists, click "Add A Key" button
 - copy "API Key" value and insert it into aMember MailChimp plugin settings (this page) and click "Save"
 - go to aMember CP -> Protect Content -> Newsletters, you will be able to define who and how can 
   subscribe to your MailChimp lists. You can create lists in <a href='http://www.mailchimp.com/' target='_blank'>MailChimp Website</a>
   
   

CUT;
    }
}

class Am_Mailchimp_Api extends Am_HttpRequest
{
    /** @var Am_Plugin_Mailchimp */
    protected $plugin;
    protected $vars = array(); // url params
    protected $params = array(); // request params
    
    public function __construct(Am_Newsletter_Plugin_Mailchimp $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct();
        $this->setMethod(self::METHOD_POST);
    }
    public function sendRequest($method, $params)
    {
        $this->vars = $params;
        $this->vars['apikey'] = $this->plugin->getConfig('api_key');
        $this->vars['method'] = $method;

        
        list($_, $server) = explode('-', $this->plugin->getConfig('api_key'), 2);
        $server = filterId($server);
        if (empty($server))
            throw new Am_Exception_Configuration("Wrong API Key set for MailChimp");
        $url = sprintf('http://%s.api.mailchimp.com/1.3/', $server);
        $url .= '?' . http_build_query($this->vars);
        $this->setUrl($url);
        $ret = parent::send();
        if ($ret->getStatus() != '200')
        {
            throw new Am_Exception_InternalError("MailChimp API Error, is configured API Key is wrong");
        }
        $arr = json_decode($ret->getBody(), true);
        if (!$arr)
            throw new Am_Exception_InternalError("MailChimp API Error - unknown response [" . $ret->getBody() . "]");
        return $arr;
    }
}