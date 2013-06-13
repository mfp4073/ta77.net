<?php

class Am_Newsletter_Plugin_Aweber extends Am_Newsletter_Plugin
{
    const APP_ID = '299631d7';
    
    protected function _initSetupForm(Am_Form_Setup $form)
    {
        $url = 'https://auth.aweber.com/1.0/oauth/authorize_app/' . self::APP_ID;
        $el = $form->addTextarea('auth', array('cols'=>80, 'rows' => 4))
            ->setLabel(
                "aWeber App Authorization Code\n".
                "get it on <a target='_blank' href='$url'>aWeber Website</a>");
        $el->addRule('regex', 'Invalid value', '/^[a-zA-Z0-9]+\|[a-zA-Z0-9]+\|[a-zA-Z0-9]+\|[a-zA-Z0-9]+\|[a-zA-Z0-9]+\|\s*$/');
        if ($this->getConfig('auth') && !$this->getConfig('access.access_token'))
        {
            if (!empty($_GET['oauth_token']))
            {
                $api = $this->getApi();
                $api->user->tokenSecret = $_COOKIE['requestTokenSecret'];
                $api->user->requestToken = $_GET['oauth_token'];
                $api->user->verifier = $_GET['oauth_verifier'];
                list($accessToken, $accessTokenSecret) = $api->getAccessToken();
                
                $this->getDi()->config->saveValue('newsletter.aweber.access', 
                    array(
                        'access_token' => $accessToken,
                        'access_secret'  => $accessTokenSecret,
                    ));
                Am_Controller::redirectLocation(REL_ROOT_URL . "/admin-setup/aweber");
                return;
            } else {
                $api = $this->getApi();
                $callbackUrl = Am_Controller::getFullUrl();
                list($requestToken, $requestTokenSecret) = $api->getRequestToken($callbackUrl);
                Am_Controller::setCookie('requestTokenSecret', $requestTokenSecret);

                $form->addStatic()->setLabel('Access Tokens')
                    ->setContent(sprintf('Access tokens are empty or expired, %sclick this link%s to update', 
                        '<a href="'.Am_Controller::escape($api->getAuthorizeUrl()).'">', '</a>'));
            }
        }
        
        $fields = $this->getDi()->userTable->getFields(true);
        $ff = $form->addMagicSelect('fields')->setLabel("Pass additional fields to AWeber\nfields must be configured in AWeber with exactly same titles\nelse API calls will fail and users will not be added\n\nBy default the plugin passes \"email\" and \"name\"\nfields to Aweber, so usually you do not need to select \nthat fields to send as additional fields.
");
        $ff->loadOptions(array_combine($fields, $fields));
    }
    
    public function isConfigured()
    {
        return (bool)$this->getConfig('auth');
    }
    function getApi()
    {
        if(!class_exists('AWeberException', false))
            require_once dirname(__FILE__) . '/api.php';
        
        $x = explode('|', $this->getConfig('auth'));
        $api = new AWeberAPI($x[0], $x[1]);
        return $api;
    }
    /** @return AWeberCollection */
    function getAccount()
    {
        $api = $this->getApi();
        $access = $this->getConfig('access');
        if (!$access['access_token'])
            throw new Am_Exception_Configuration("AWeber Keys expired");
        $account = $api->getAccount($access['access_token'], $access['access_secret']);
        return $account;
    }

    public function getLists()
    {
        $ret = array( );
        foreach ($this->getAccount()->lists as $list)
            $ret[$list->id] = array('title' => $list->name, );
        return $ret;
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        foreach ($addLists as $listId)
        {
            $list = $this->getAccount()->lists->getById($listId);
            try {
                
                $custom_fields = array();
                foreach ($this->getConfig('fields', array()) as $f)
                {
                    $custom_fields[$f] = (string)$user->get($f);
                    if (!strlen($custom_fields[$f]))
                        $custom_fields[$f] = (string)$user->data()->get($f);
                }
                $info = array(
                    'email' => $user->email,
                    'name'  => $user->getName(),
                    'ip_address' => $user->remote_addr,
                );
                if ($custom_fields)
                    $info['custom_fields'] = $custom_fields;

                $subs = $list->subscribers->create($info);
                
            } catch (AWeberAPIException $e) {
                if ($e->getMessage() == 'email: Subscriber already subscribed.')
                    return true;
                $this->getDi()->errorLogTable->log($e);
                return false;
            }
            $attr = $subs->attrs();
            $id = $attr['id'];
            $user->data()->set('aweber.' . $listId, $id)->update();
        }
        foreach ($deleteLists as $listId)
        {
            $uid = $user->data()->get('aweber.' . $listId);
            if (!$uid) return true;

            $list = $this->getAccount()->lists->getById($listId);
            $sub = $list->subscribers->getById($uid);
            $res = $sub->delete();

            $user->set('aweber.'.$listId, null)->update();
        }
        return true;
    }

    function getReadme()
    {
        return <<<CUT
Aweber Plugin Readme
   
To configure access to AWeber API, click "get it on aWeber Website" link in the
setup form, you will be asked to allow API access. Allow it, copy access
code from AWeber, and paste it to aMember configuration field. After pressing
"Save", you will be asked to update "Access Tokens". Click it to update.
Once Access Tokens are updated, you are able to use AWeber service with aMember.
Go to aMember CP -> Protect Content -> Newsletters. All your AWeber lists
will be automatically fetched from AWeber website and added to table. You can 
configure newsletter access as usual.
CUT;
    }
}
