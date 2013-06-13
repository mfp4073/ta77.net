<?php

class Am_Newsletter_Plugin_GetResponse extends Am_Newsletter_Plugin
{
    function _initSetupForm(Am_Form_Setup $form)
    {
        $el = $form->addPassword('api_key', array('size' => 40))->setLabel('GetResponse API Key'.
            "\n" . 'You can get your API Key <a target="_blank" href="https://app.getresponse.com/my_api_key.html">here</a>');
        $el->addRule('required');
    }

    function  isConfigured()
    {
        $api_key = $this->getConfig('api_key');
        return !empty($api_key);
    }

    /** @return Am_Plugin_GetResponse */
    function getApi()
    {
        return new Am_GetResponse_Api($this->getConfig('api_key'));
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        $api = $this->getApi();
        foreach ($addLists as $list_id)
        {
            $api->call('add_contact', array(
                'campaign' => $list_id,
                'name' => $user->getName(),
                'email' => $user->email,
                'cycle_day' => 0,
                'ip' => $user->remote_addr
            ));
        }

        if (!empty($deleteLists)) {
            $res = $api->call('get_contacts', array(
                "campaigns" => $deleteLists,
                'email' => array(
                        'EQUALS' => $user->email
                    )
            ));

            foreach ($res as $id => $contact) {
                $api->call('delete_contact', array(
                    'contact' => $id
                ));
            }
        }

        return true;
    }

    public function getLists()
    {
        $api = $this->getApi();
        $ret = array();
        $lists = $api->call('get_campaigns');
        foreach ($lists as $id => $l)
            $ret[$id] = array(
                'title' => $l['name'],
            );
        return $ret;
    }
    
    public function getReadme()
    {
        return <<<CUT
GetResponse plugin readme
       
This module allows aMember Pro users to subscribe/unsubscribe from e-mail lists
created in GetResponse. To configure the module:

 - go to <a target="_blank" href="https://app.getresponse.com/my_api_key.html">app.getresponse.com/my_api_key.html</a>
 - copy "API Key" value and insert it into aMember GetResponse plugin settings (this page) and click "Save"
 - go to aMember CP -> Protect Content -> Newsletters, you will be able to define who and how can 
   subscribe to your GetResponse lists. You can create lists in <a href="http://www.getresponse.com/" target="_blank">GetResponse Website</a>
CUT;
    }
}

class Am_GetResponse_Api extends Am_HttpRequest
{
    protected $api_key = null;
    protected $endpoint = 'http://api2.getresponse.com';
    protected $lastId = 1;
    
    public function __construct($api_key)
    {
        $this->api_key = $api_key;
        parent::__construct();
        $this->setMethod(self::METHOD_POST);
        $this->setUrl($this->endpoint);
    }

    public function call($method,  $params = null)
    {
        $this->setBody(json_encode($this->prepCall($method, $params)));

        $ret = parent::send();
        if ($ret->getStatus() != '200')
            throw new Am_Exception_InternalError("GetResponse API Error, is configured API Key is wrong");

        $arr = json_decode($ret->getBody(), true);
        if (!$arr)
            throw new Am_Exception_InternalError("GetResponse API Error - unknown response [" . $ret->getBody() . "]");

        if (isset($arr['error']))
            throw new Am_Exception_InternalError("GetResponse API Error - {$arr['error']['code']} : {$arr['error']['message']}");

        return $arr['result'];
    }

    protected function prepCall($method,  $params = null) {
        $p = array($this->api_key);
        if (!is_null($params)) array_push($p, $params);

        $call = array(
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $p,
            'id' => $this->lastId++
        );

        return $call;
    }
}