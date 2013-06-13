<?php

class Am_Newsletter_Plugin_Activecampaign extends Am_Newsletter_Plugin
{
    static $api;
    function _initSetupForm(Am_Form_Setup $form)
    {
        $el = $form->addAdvRadio('api_type')
            ->setLabel(array(___('Version of script')))
            ->loadOptions(array(
                '0'  => ___('Downloaded on your own server'),
                '1'  => ___('Hosted at Activecampaing\'s server')));
        $form->addScript()->setScript(<<<CUT
$(document).ready(function() {
    function api_ch(val){
        $("#api_key-0").parent().parent().toggle(val == '1');
        $("#api_user-0").parent().parent().toggle(val == '0');
        $("#api_password-0").parent().parent().toggle(val == '0');    
    }
    $("input[type=radio]").change(function(){ api_ch($(this).val()); }).change();
    api_ch($("input[type=radio]:checked").val());
});
CUT
);
        $form->addText('api_url', array('size' => 40))->setLabel('Activecampaign API url'.
            "\nit should be with http://");
        $form->addText('api_key', array('size' => 40))->setLabel('Activecampaign API Key');
        
        $form->addText('api_user', array('size' => 40))->setLabel('Activecampaign Admin Login');
        $form->addPassword('api_password', array('size' => 40))->setLabel('Activecampaign Admin Password');
    }
    /** @return Am_Plugin_Mailchimp */
    function getApi()
    {
        if(!isset($this->api))
            $this->api = new Am_Activecampaign_Api($this);
        return $this->api;
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        $api = $this->getApi(); 
        $lists = array();
        foreach ($addLists as $id){
            $lists["p[$id]"]=$id;
            $lists["status[$id]"]=1;
        }
        foreach ($deleteLists as $id){
            $lists["p[$id]"]=$id;
            $lists["status[$id]"]=2;
        }
        $uid=$user->data()->get('activecampaign_subscriber_id');
        if(!empty($uid)){
            $ret = $api->sendRequest('subscriber_edit',array_merge(array(
                'id' => $uid,
                'email' => $user->email,
                'first_name' => $user->name_f,
                'last_name' => $user->name_l
            ),$lists));
            if (!$ret) return false;
        }
        else{
            $ret = $api->sendRequest('subscriber_add',array_merge(array(
                'email' => $user->email,
                'first_name' => $user->name_f,
                'last_name' => $user->name_l
            ),$lists));            
            if (!$ret) return false;
            $user->data()->set('activecampaign_subscriber_id', $ret['subscriber_id'])->update();
        }
        $user->data()->set('activecampaign_subscriber_lists', serialize($lists))->update();
        return true;
    }
    function onUserAfterUpdate(Am_Event_UserAfterUpdate $event)
    {
        $user = $event->getUser();
        $uid = $user->data()->get('activecampaign_subscriber_id');
        if(!empty($uid))
        {
            $api = $this->getApi(); 
            $ret = $api->sendRequest('subscriber_edit',array_merge(array(
                'id' => $uid,
                'email' => $user->email,
                'first_name' => $user->name_f,
                'last_name' => $user->name_l
            ),unserialize($user->data()->get('activecampaign_subscriber_lists'))));
        }        
    }
    
    function getUserByEmail($email)
    {
        return $api->sendRequest('subscriber_view_email', array('email' => $email));        
    }
    
   public function getLists()
    {
        $api = $this->getApi();
        $ret = array();
        $lists = $api->sendRequest('list_list', array('ids' => 'all'));
        foreach ($lists as $l){
            $ret[$l['id']] = array(
                'title' => $l['name'],
            );
        }
        return $ret;
    }
    
    public function getReadme()
    {
        return <<<CUT
   Activecampaign plugin readme
       
This module allows aMember Pro users to subscribe/unsubscribe from e-mail lists
created in Activecampaign.

  - copy "API Key" and "API Url" values from your Activecampaign account and insert it into aMember Activecampaign plugin settings (this page) and click "Save"
  - go to aMember CP -> Protect Content -> Newsletters, you will be able to define who and how can subscribe to your Activecampaign lists.
   
   

CUT;
    }
}

class Am_Activecampaign_Api extends Am_HttpRequest
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
    public function sendRequest($api_action, $params)
    {
        $this->vars = $params;
        $this->vars['api_key'] = $this->plugin->getConfig('api_key');
        $this->vars['api_user'] = $this->plugin->getConfig('api_user');
        $this->vars['api_pass'] = $this->plugin->getConfig('api_password');
        $this->vars['api_action'] = $api_action;
        $this->vars['api_output'] = 'serialize';

        
        $this->setUrl($url = $this->plugin->getConfig('api_url').'/admin/api.php');
        $this->addPostParameter($this->vars);
        $ret = parent::send();
        if ($ret->getStatus() != '200')
        {
            throw new Am_Exception_InternalError("Activecampaign API Error, is configured API Key is wrong");
        }
        $arr = unserialize($ret->getBody());
        if (!$arr)
            throw new Am_Exception_InternalError("Activecampaign API Error - unknown response [" . $ret->getBody() . "]");
        if($arr['result_code']!=1)
            throw new Am_Exception_InternalError("Activecampaign API Error - code [". $arr['result_code']."]response [" . $arr['result_message'] . "]");
        unset($arr['result_code'],$arr['result_message'],$arr['result_output']);
        return $arr;
    }
}