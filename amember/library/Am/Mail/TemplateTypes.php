<?php

/**
 * Registry of e-mail template types and its properties
 * @package Am_Mail_Template
 */
class Am_Mail_TemplateTypes extends ArrayObject
{
    protected $tagSets = array();
    
    static protected $instance;

    /** @return Am_Mail_TemplateTypes */
    static function getInstance()
    {
        if (!self::$instance)
            self::$instance = self::createInstance();
        return self::$instance;
    }

    public function find($id)
    {
        return $this->offsetExists($id) ? $this->offsetGet($id) : null;
    }
    
    /** @return Am_Mail_TemplateTypes */
    static function createInstance()
    {
        $o = new self;

        $o->tagSets = array(
            'user' => array(
                '%user.name_f%' => 'User First Name',
                '%user.name_l%' => 'User Last Name',
                '%user.login%' => 'Username',
                '%user.email%' => 'E-Mail',
                '%user.user_id%' => 'User Internal ID#',
                '%user.street%' => 'User Street',
                '%user.street2%' => 'User Street (Second Line)',
                '%user.city%' => 'User City',
                '%user.state%' => 'User State',
                '%user.zip%' => 'User ZIP',
                '%user.country%' => 'User Country',
                '%user.status%' => 'User Status (0-pending, 1-active, 2-expired)',
            ),
            'invoice' => array(
                '%invoice.invoice_id%' => 'Invoice Internal ID#',
                '%invoice.public_id%' => 'Invoice Public ID#',
                '%invoice.first_total%' => 'Invoice First Total',
                '%invoice.second_total%' => 'Invoice Second Total',
            )
        );
        
        $event = new Am_Event(Am_Event::SETUP_EMAIL_TEMPLATE_TYPES);
        Am_Di::getInstance()->hook->call($event);

        $res = $event->getReturn();

        $o->exchangeArray(array_merge(array(
            'registration_mail' =>  array(
                'id' => 'registration_mail',
                'title' => 'Registration E-Mail',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user', 'password' => 'Plain-Text Password'),
            ),
            'changepass_mail' =>  array(
                'id' => 'changepass_mail',
                'title' => 'Password Change E-Mail',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user', 'password' => 'Plain-Text Password'),
            ),
            'send_signup_mail' =>  array(
                'id' => 'send_signup_mail',
                'title' => 'Send Signup Mail',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user'),
            ),
            'mail_payment_admin' => array(
                'id' => 'mail_payment_admin',
                'title' => 'Mail Payment Admin',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user'),
            ),
            'send_payment_mail' => array(
                'id' => 'send_payment_mail',
                'title' => 'Send Payment Mail',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user','invoice'),
            ),
            'send_payment_admin' => array(
                'id' => 'send_payment_admin',
                'title' => 'Send Payment Admin',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user','invoice'),
            ),
            'manually_approve' => array(
                'id' => 'manually_approve',
                'title' => 'Manually Approve',
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'vars' => array('user'),
            ),
            'manually_approve_admin' => array(
                'id' => 'manually_approve_admin',
                'title' => 'Manually Approve Admin',
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'vars' => array('user'),
            ),
            'invoice_approval_wait_user' => array(
                'id' => 'invoice_approval_wait_user',
                'title' => 'Manually Approve Invoice',
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'vars' => array('user','invoice'),
            ),
            'invoice_approval_wait_admin' => array(
                'id' => 'invoice_approval_wait_admin',
                'title' => 'Manually Approve Invoice Admin',
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'vars' => array('user', 'invoice'),
            ),
            'invoice_approved_user' => array(
                'id' => 'invoice_approved_user',
                'title' => 'Invoice Approved',
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'vars' => array('user', 'invoice'),
            ),
            'card_expires' =>
            array(
                'id' => 'card_expires',
                'title' => 'Card Expires',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user'),
            ),
            'send_security_code' =>
            array(
                'id' => 'send_security_code',
                'title' => 'Send Security Code',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' =>  array('user', 'code' => 'Security Code', 'url' => 'Click Url'),
            ),
            'verify_email_signup' =>
            array(
                'id' => 'verify_email_signup',
                'title' => 'Verify Email Signup',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user'),
            ),
            'verify_email_profile' =>
            array(
                'id' => 'verify_email_profile',
                'title' => 'Verify Email Profile',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user'),
            ),
            'autoresponder' => 
            array(
                'id' => 'autoresponder',
                'title' => 'Auto-Responder',
                'mailPeriodic' => Am_Mail::REGULAR,
                'vars' => array('user'),
            ),
            'expire' => 
            array(
                'id' => 'expire',
                'title' => 'Expiration E-Mail',
                'mailPeriodic' => Am_Mail::REGULAR,
                'vars' => array('user', 'expires' => 'Expiration Date'),
            ),
            'pending_to_user' => array(
                'id' => 'pending_to_user',
                'title' => 'Pending invoice notifications to user',
                'mailPeriodic' => Am_Mail::REGULAR,
                'vars' => array('user', 'invoice', 'day'=>'Day of Notification Sending', 'product_title'=>'Product(s) Title'),
            ),
            'pending_to_admin' => array(
                'id' => 'pending_to_admin',
                'title' => 'Pending invoice notifications to user',
                'mailPeriodic' => Am_Mail::REGULAR,
                'vars' => array('user', 'invoice', 'day'=>'Day of Notification Sending', 'product_title'=>'Product(s) Title'),
            ),
            'max_ip_actions_admin' => 
            array(
                'id' => 'max_ip_actions_admin',
                'title' => 'Email admin regarding account sharing',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user'),
            ),
            'max_ip_actions_user' => 
            array(
                'id' => 'max_ip_actions_user',
                'title' => 'Email user regarding account sharing',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user'),
            ),
            'mail_cancel_member' => array(
                'id' => 'mail_cancel_member',
                'title' => 'Send Cancel Notifications to User',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user', 'invoice'),
            ),
            'mail_cancel_admin' => array(
                'id' => 'mail_cancel_admin',
                'title' => 'Send Cancel Notifications to Admin',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user', 'invoice'),
            ),
        ), $res));

        return $o;
    }
    
    /**
     * Return array - key => value of available options for template with given $id
     * @param type $id
     * @return array
     */
    public function getTagsOptions($id)
    {
        $record = @$this[$id];
        $ret = array(
            '%site_title%' => 'Site Title',
            '%root_url%' => 'aMember Root URL',
            '%admin_email%' => 'Admin E-Mail Address',
        );
        if (!$record || empty($record['vars']))
            return $ret;
        foreach ($record['vars'] as $k => $v)
        {
            if (is_int($k)) // tag set
                $ret = array_merge($ret, $this->tagSets[$v]);
            else // single variable
                $ret['%'.$k.'%'] = $v;
        }
        return $ret;
    }

    public function add($id, $title, $mailPeriodic, array $vars)
    {
        $this[$id] = array('id' => $id, 'title' => $title, 'mailPeriodic' => $mailPeriodic, 'vars' => $vars);
    }

}
