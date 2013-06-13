<?php

class Am_Plugin_Facebook extends Am_Plugin
{
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.2.17';
    const FACEBOOK_UID = 'facebook-uid';
    const FACEBOOK_LOGOUT = 'facebook-logout';

    const NOT_LOGGED_IN = 0;
    const LOGGED_IN = 1;
    const LOGGED_AND_LINKED = 2;
    const LOGGED_OUT = 3;
    
    protected $status = null; //self::NOT_LOGGED_IN;
    /** @var User */
    protected $linkedUser;
    /** @var array */
    protected $fbProfile = null;
    
    public function isConfigured()
    {
        return $this->getConfig('app_id') && $this->getConfig('app_secret');
    }
    function onSetupForms(Am_Event_SetupForms $event)
    {
        $form = new Am_Form_Setup('facebook');
        $form->setTitle('Facebook');
        
        $fs = $form->addFieldset()->setLabel(___('FaceBook Application'));
        $fs->addText('app_id')->setLabel(___('FaceBook App ID'));
        $fs->addText('app_secret', array('size' => 40))->setLabel(___('Facebook App Secret'));
        
        $fs = $form->addFieldset()->setLabel(___('Features'));
        $gr = $fs->addCheckboxedGroup('like')->setLabel(___('Add "Like" button'));
        $gr->addStatic()->setContent(___('Like Url'));
        $gr->addText('likeurl', array('size' => 40));
        $form->setDefault('likeurl', ROOT_URL);
        
        $fs->addAdvCheckbox('no_signup')->setLabel(___('Do not add to Signup Form'));
        $fs->addAdvCheckbox('no_login')->setLabel(___('Do not add to Login Form'));
        
        $fs->addAdvCheckbox('create_account')
            ->setLabel(array(
                ___('Create account from login form'), 
                ___("Create account for facebook user automatically")
                ));
        $fs->addSelect('add_access', null, array(
            'options' => array('' => '-- Do not add access --') + Am_Di::getInstance()->productTable->getOptions()
            ))
            ->setLabel(___('Additionaly add access to this product'));
        $form->addFieldsPrefix('misc.facebook.');
        $form->addScript()->setScript(<<<EOT
    jQuery(document).ready(function ($){
        $('#create_account-0').change(function(){
            $("#add_access-0").closest('div.row').toggle(this.checked);
        });
    });
EOT
            );
        $this->_afterInitSetupForm($form);
        $event->addForm($form);
    }
    function onInitFinished(Am_Event $event)
    {
        $blocks = $this->getDi()->blocks;
        if (!$this->getConfig('no_login'))
            $blocks->add(
                new Am_Block('login/form/after', null, 'fb-login', $this, 'fb-login.phtml')
            );
        if (!$this->getConfig('no_signup'))
            $blocks->add(
                new Am_Block('signup/form/before', null, 'fb-signup', $this, 'fb-signup.phtml')
            );
        if ($this->getConfig('like'))
            $blocks->add(
                new Am_Block('member/main/right/top', null, 'fb-like', $this, 'fb-like.phtml')
            );
    }
    function onSignupUserAdded(Am_Event $event)
    {
        $user = $event->getUser();
        // validate if user is logged-in to Facebook
        $api = $this->getApi();
        if ($api->getSignedRequest() && ($fbuid = $api->getUser()))
        {
            $user->data()->set(self::FACEBOOK_UID, $fbuid)->update();
        }
    }
    /** @return Facebook|null */
    function getApi()
    {
        if (!$this->getConfig('app_id'))
        {
            throw new Am_Exception_Configuration("Facebook plugins is not configured");
        }
        require_once dirname(__FILE__) . '/facebook-sdk.php';
        return new Am_Facebook(array(
            'appId'  => $this->getConfig('app_id'),
            'secret' => $this->getConfig('app_secret'),
            'cookie' => true,
        ), $this->getSession());
    }
    function getSession()
    {
        static $session;
        if (empty($session))
            $session = new Zend_Session_Namespace('am_facebook');
        return $session;
    }
    
    
    /**
     * Create account in aMember for user who is logged in facebook. 
     */
    function createAccount()
    {
        /* Search for account by email address */
        $user = $this->getDi()->userTable->findFirstByEmail($this->getFbProfile('email'));
        if(empty($user))
        {
            // Create account for user;
            $user = $this->getDi()->userRecord;
            $user->email = $this->getFbProfile('email');
            $user->name_f = $this->getFbProfile('first_name');
            $user->name_l = $this->getFbProfile('last_name');
            $user->generateLogin();
            $user->generatePassword();
            $user->insert();
        }
        
        $user->data()->set(self::FACEBOOK_UID, $this->getFbProfile('id'))->update();
        
        if($product_id = $this->getConfig('add_access'))
        {
            $product = $this->getDi()->productTable->load($product_id);
            $billingPlan = $this->getDi()->billingPlanTable->load($product->default_billing_plan_id);
            
            $access = $this->getDi()->accessRecord;
            $access->product_id = $product_id;
            $access->begin_date = $this->getDi()->sqlDate;
            
            $period = new Am_Period($billingPlan->first_period);
            $access->expire_date = $period->addTo($access->begin_date);
            
            $access->user_id = $user->pk();
            $access->insert();
        }
        
        return $user;
        
    }
    
    function onAuthCheckLoggedIn(Am_Event_AuthCheckLoggedIn $event)
    {
        $status = $this->getStatus();
        if ($status == self::LOGGED_AND_LINKED)
            $event->setSuccessAndStop($this->linkedUser);
        elseif ($status == self::LOGGED_OUT && !empty($_GET['fb_login']))
        {
            $this->linkedUser->data()->set(self::FACEBOOK_LOGOUT, null)->update();
            $event->setSuccessAndStop($this->linkedUser);
        }
        elseif($status == self::LOGGED_IN && $this->getDi()->request->get('fb_login') && $this->getConfig('create_account'))
        {
            $this->linkedUser = $this->createAccount();
            $event->setSuccessAndStop($this->linkedUser);
        }
    }
    function onAuthAfterLogout(Am_Event_AuthAfterLogout $event)
    {
        $this->getSession()->unsetAll();
        $domain = Zend_Controller_Front::getInstance()->getRequest()->getHttpHost();
        Am_Controller::setCookie('fbsr_'.$this->getConfig('app_id'), null, time() - 3600*24, "/");
        Am_Controller::setCookie('fbm_'.$this->getConfig('app_id'), null, time() - 3600*24, "/");
        Am_Controller::setCookie('fbsr_'.$this->getConfig('app_id'), null, time() - 3600*24, "/", $domain, false);
        Am_Controller::setCookie('fbm_'.$this->getConfig('app_id'), null, time() - 3600*24, "/", $domain, false);
        $event->getUser()->data()->set(self::FACEBOOK_LOGOUT, true)->update();
    }
    function onAuthAfterLogin(Am_Event_AuthAfterLogin $event)
    {
        if (($this->getStatus() == self::LOGGED_IN) && $this->getFbUid())
        {
            $event->getUser()->data()->set(self::FACEBOOK_UID, $this->getFbUid())->update();
        }
    }
    
    function getStatus()
    {
        if ($this->status !== null) return $this->status;
        $this->linkedUser = null;
        if ($id = $this->getApi()->getUser())
        {
            $user = $this->getDi()->userTable->findFirstByData(self::FACEBOOK_UID, $id);
            if ($user)
            {
                $this->linkedUser = $user;
                if ($user->data()->get(self::FACEBOOK_LOGOUT))
                    $this->status = self::LOGGED_OUT;
                else
                    $this->status = self::LOGGED_AND_LINKED;
            } else {
                $this->status = self::LOGGED_IN;
            }
        } else {
            $this->status = self::NOT_LOGGED_IN;
        }
        return $this->status;
    }
    
    /** @return User */
    function getLinkedUser()
    {
        return $this->linkedUser;
    }
    /** @return int FbUid */
    function getFbUid()
    {
        return $this->getApi()->getUser();
    }
    /** @return facebook info */
    function getFbProfile($fieldName)
    {
        if (is_null($this->fbProfile) && $this->getFbUid())
        {
            $this->fbProfile = $this->getApi()->api('/me');
        }
        return !empty($this->fbProfile[$fieldName]) ? $this->fbProfile[$fieldName] : null;
    }
    
    function renderConnect()
    {
        return sprintf('<img src="%s" width="%d" height="%d" alt="%s"/>',
            Am_Controller::escape(REL_ROOT_URL . '/misc/facebook/connect-btn'),
            107, 25, ___("Connect with Facebook")
        );
        //return ___('Connect with Facebook');
    }
    function renderLogin()
    {
        return sprintf('<img src="%s" width="%d" height="%d" alt="%s"/>',
            Am_Controller::escape(REL_ROOT_URL . '/misc/facebook/login-btn'),
            107, 25, ___("Login using Facebook")
        );
        //return ___('Login using Facebook');
    }
    public function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        switch ($action = $request->getActionName())
        {
            case 'connect-btn':
            case 'login-btn':
                $response->setHeader('Content-Type', 'image/png', true);
                $response->setHeader('Expires', gmdate('D, d M Y H:i:s', time()+3600*24).' GMT', true);
                readfile(dirname(__FILE__) . '/facebook-connect.png');
                break;
            default:
                throw new Am_Exception_InputError("Wrong request: [$action]");
        }
    }
    
    function getReadme()
    {
        return <<<CUT
aMember Pro version 4 includes Facebook integration plugin. It allows customer 
to signup and login to your website using Facebook account, as well as adds 
"Like" button to member area.

To enable and configure the plugin, follow these instructions:

* Go to aMember CP -> Setup -> Plugins and enable facebook plugin;
* If you have not done it before, you need to register your Application on Facebook. 
  Go to https://developers.facebook.com/apps and click Create New App button
* Enter App Display Name - it will be displayed to customer when he is asked 
  to grant access to information during login, and click Continue
* Enter your Contact Email
* Enter your domain name (without www) into App Domain field
* In the Select how your app integrates with Facebook, click on Website
* Finally, press Save Changes
* Copy & paste App ID and App Secret. You will need these values on the next step
* Return back to aMember Cp -> Setup -> Facebook and insert App ID and 
  App Secret values into corresponding fields. Optionally, you can add 
  Like button into members area. 
  Usually it points to your site homepage url : http://www.example.com/ 
CUT;
    }
    
}
