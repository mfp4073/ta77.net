<?php

/**
 * E-Mail sending class for aMember. Changes:
 *  - type of message urgency - to queue handling
 * @package Am_Mail
 */
class Am_Mail extends Zend_Mail {
    const REGULAR = 10;
    const ADMIN_REQUESTED = 20;
    const USER_REQUESTED = 30;
    protected $periodic = self::REGULAR;

    const PRIORITY_HIGH = 9;
    const PRIORITY_MEDIUM = 5;
    const PRIORITY_LOW = 0;
    protected $priority = null;
    
    const LINK_USER = 1;
    protected $addUnsubscribeLink = false;

    const UNSUBSCRIBE_HTML = '
<br />
<font color="gray">To unsubscribe from our periodic e-mail messages, please click the following <a href="%link%">link</a></font>
<br />
';

    const UNSUBSCRIBE_TXT = '

-------------------------------------------------------------------
To unsubscribe from our periodic e-mail messages, please click the
following link:
  %link%
-------------------------------------------------------------------

';

    public function __construct($charset = 'utf-8')
    {
        parent::__construct($charset);
    }
    
    public function setPeriodic($periodic){ $this->periodic = $periodic ; return $this; }
    public function getPeriodic($periodic){ return $this->periodic; }
    /** Should the e-mail be sent immediately, or it can be put to queue ? */
    public function isInstant(){ return $this->periodic == self::USER_REQUESTED; }
    /** @return int calculate from periodic+priority, bigger will stay higher in the queue and will be set faster */
    public function getPriority(){ return (int)$this->priority + (int)$this->periodic;}
    /** Set mail order in the queue, by default it will be set based on "periodic" */
    public function setPriority($priority){ $this->priority = $priority; return $this; }
    /**
     * Add unsubscibe link of given type (see class constants)
     * This must be called before adding e-mail body
     * @param int $type
     */
    public function addUnsubscribeLink($type = self::LINK_USER){
        if ($this->_bodyText || $this->_bodyHtml)
            throw new Am_Exception_InternalError("Body is already added, could not do " . __METHOD__);
        $this->addUnsubscribeLink = $type;
    }
    public function clearUnsubscribeLink(){
        if ($this->_bodyText || $this->_bodyHtml)
            throw new Am_Exception_InternalError("Body is already added, could not do " . __METHOD__);
        $this->addUnsubscribeLink = false;
    }
    /**
     * @param string $content - will be modified
     * @param bool $isHtml
     * @return null
     */
    protected function _addUnsubscribeLink(& $content, $isHtml){
        if (!$this->addUnsubscribeLink) return ;
        if (Am_Di::getInstance()->config->get('disable_unsubscribe_link')) return; //disabled at all
        $e = @$this->_to[0];
        if ($e=="") {
            trigger_error("E-Mail address is empty in " . __METHOD__.", did you call addUnsubscribeLink before adding receipients?", E_USER_WARNING);
            return; // no email address
        }
        $link = Am_Mail::getUnsubscribeLink($e, $this->addUnsubscribeLink);
        if ($isHtml)
            $out = Am_Di::getInstance()->config->get('unsubscribe_html', Am_Mail::UNSUBSCRIBE_HTML);
        else
            $out = Am_Di::getInstance()->config->get('unsubscribe_txt', Am_Mail::UNSUBSCRIBE_TXT);
        $out = "\r\n" .  str_replace('%link%', $isHtml ? Am_Controller::escape($link) : $link, $out);
        if (!$isHtml) {
            $content .= "\r\n" . $out;
        } else {
            $content = str_ireplace('</body>', $out . '</body>', $content, $replaced);
            if (!$replaced)
                $content .= $out;
        }
    }
    
    static function getUnsubscribeLink($email, $type = self::LINK_USER)
    {
        $link = ROOT_SURL;
        $sign = Am_Di::getInstance()->app->hash(Am_Di::getInstance()->app->getSiteKey() . $email . 'MAIL-USER', 10);
        $link .= sprintf('/unsubscribe?e=%s&s=%s',
                    urlencode($email), $sign);
        return $link;
    }
    static function validateUnsubscribeLink($email, $sign, $type = self::LINK_USER)
    {
        return $sign === Am_Di::getInstance()->app->hash(Am_Di::getInstance()->app->getSiteKey() . $email . 'MAIL-USER', 10);
    }
    
    public function setBodyHtml($html, $charset = null, $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE) {
        $this->_addUnsubscribeLink($html, true);
        parent::setBodyHtml($html, $charset, $encoding);
    }
    public function setBodyText($txt, $charset = null, $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE) {
        $this->_addUnsubscribeLink($txt, false);
        parent::setBodyText($txt, $charset, $encoding);
    }

    public function send($transport = null) {
        return parent::send($transport);
    }
    /**
     * Store itself into a string for later quick loading
     * Attachments (aka parts) must be handled separately
     * @param array parts are Am_Mime_Part objects to be saved separately
     * @return string
     */
    public function serialize(array & $parts){
        $parts = $this->_parts;
        foreach ($parts as $p) {
            if ($p instanceof Am_Mime_Part) {
            }
        }
        $ret = serialize($this);
        $this->_parts = $parts;
    }
    public function serialzeAttachments(){
        $ret = array();
        foreach ($this->_parts as $part) {
            $part = new Zend_Mime_Part();
        }
    }
    /**
     * Restore an Am_Mail object from a string
     * Attachments must be restored separately
     */
    public static function unserialize($string){
        return unserialize($string);
    }
    static function initDefaults()
    {
        self::setDefaultFrom(
            Am_Di::getInstance()->config->get('admin_email_from', Am_Di::getInstance()->config->get('admin_email')),
            Am_Di::getInstance()->config->get('admin_email_name', Am_Di::getInstance()->config->get('site_title')));
        self::setDefaultTransport(Am_Mail_Queue::getInstance());
    }
    public function createAttachment($body,
                                     $mimeType    = Zend_Mime::TYPE_OCTETSTREAM,
                                     $disposition = Zend_Mime::DISPOSITION_ATTACHMENT,
                                     $encoding    = Zend_Mime::ENCODING_BASE64,
                                     $filename    = null)
    {
        $mp = new Am_Mime_Part($body); // it was only the change
        $mp->encoding = $encoding;
        $mp->type = $mimeType;
        $mp->disposition = $disposition;
        $mp->filename = $filename;
        $this->addAttachment($mp);
        return $mp;
    }
    /**
     * Set message To: admin
     * @return Am_Mail
     */
    public function toAdmin(){
        $this->clearRecipients();
        $this->addTo(Am_Di::getInstance()->config->get('admin_email'), Am_Di::getInstance()->config->get('site_title') . ' Admin');
        if (Am_Di::getInstance()->config->get('copy_admin_email'))
            foreach (preg_split("/[,;]/",Am_Di::getInstance()->config->get('copy_admin_email')) as $email)
                if ($email) $this->addBcc($email);
        return $this;
    }
}

/**
 * Just to satisfy Zend_Mail_Transport_Abstract needs
 * @internal
 * @package Am_Mail
 */
class Am_Mail_Saved {
    public $from;
    public $subject;
    public $recipients;
    function getFrom(){ return $this->from;  }
    function getSubject(){ return $this->subject; }
    function getRecipients(){ return $this->recipients; }
    function getReturnPath(){ return $this->from; }
}


/** 
 * @package Am_Mail
 * @todo put into a separate file for lazy-loading 
 */
class Am_Mail_Transport_Smtp extends Zend_Mail_Transport_Smtp {
    function sendFromSaved($from, $recipients, $body, array $headers, $subject){
        $this->_mail = new Am_Mail_Saved;
        $this->_mail->from = $from;
        $this->_mail->subject = $subject;
        $this->_mail->recipients = split(',', $recipients);
        $this->recipients = $recipients;
        $this->body = $body;
        $this->_prepareHeaders($headers);
        $this->_sendMail();
    }
}
/** 
 * @package Am_Mail
 * @todo put into a separate file for lazy-loading 
 */
class Am_Mail_Transport_Sendmail extends Zend_Mail_Transport_Sendmail {
    function sendFromSaved($from, $recipients, $body, array $headers, $subject){
        $this->_mail = new Am_Mail_Saved;
        $this->_mail->from = $from;
        $this->_mail->subject = $subject;
        $this->recipients = $recipients;
        $this->body = $body;
        $this->_prepareHeaders($headers);
        $this->_sendMail();
    }
}

/** 
 * Do not send any e-mails
 * @package Am_Mail
 */
class Am_Mail_Transport_Null extends Zend_Mail_Transport_Abstract
{
    protected function _sendMail()
    {
        // do nothing
    }
    function sendFromSaved($from, $recipients, $body, array $headers, $subject)
    {
        // do nothing
    }
}

/**
 * This is a proxy e-mail transport, it does the following:
 *   - initializes real transport when necessary using Am_Di::getInstance()->config->get() values
 *   - saves e-mail messages to log when enabled
 *   - puts not regular messages to queue instead of sending when enabled
 * @package Am_Mail
 */
class Am_Mail_Queue extends Zend_Mail_Transport_Abstract
{
    const QUEUE_DISABLED = -1;
    const QUEUE_OK = 1;
    const QUEUE_ONLY_INSTANT = 2;
    const QUEUE_FULL = 3;

    /** @var Zend_Mail_Transport_Abstract */
    protected $transport;

    protected $queueEnabled = false;
    /** @var int seconds */
    protected $queuePeriod;
    /** @var int limit of emails in $queuePeriod minutes */
    protected $queueLimit;
    /** @var int limit of periodical e-mails per hour
     * (automatically set to 80% @see $queueLimit)
     * to keep window for urgent emails like password
     * requests  */
    protected $queuePeriodicLimit;
    /** @var int days to store sent messages
     * even if that is null, aMember can anyway
     * store messages for queuing, it will then
     * be deleted automatically after 14 days if not delivered */
    protected $logDays;
    /**
     * How many messages can we send in this period? This is set
     * by @see getQueueStatus
     * @var int
     */
    protected $leftMessagesToSend = null;

    static protected $instance;
    /**
     * Singleton
     * @return Am_Mail_Queue
     */
    static public function getInstance(){
        if (self::$instance == null)
            self::$instance = new self();
        return self::$instance;
    }

    public function  __construct() {
        $this->_readConfig();
        $di = Am_Di::getInstance();
        if (APPLICATION_ENV == 'demo' || ($di->config->get('email_method') == 'disabled')) {
            $this->setTransport(new Am_Mail_Transport_Null);
        } elseif ($di->config->get('email_method') == 'smtp') {
            $host = $di->config->get('smtp_host');
            $config = array(
                'port' => $di->config->get('smtp_port', 25),
                'ssl'  => $di->config->get('smtp_security'),
            );
            if ($di->config->get('smtp_user') && $di->config->get('smtp_pass')){
                $config['username'] = $di->config->get('smtp_user');
                $config['password'] = $di->config->get('smtp_pass');
                $config['auth'] = 'login';
                $config['ssl']  = $di->config->get('smtp_security');
            }
            $this->setTransport(new Am_Mail_Transport_Smtp($host, $config));
        } elseif ($di->config->get('email_method') == 'ses') {
            $config = array(
                'accessKey' => $di->config->get('ses_id', 25),
                'privateKey'  => $di->config->get('ses_key'),
            );
            $this->setTransport(new Am_Mail_Transport_Ses($config));
        } else {
            $this->setTransport(new Am_Mail_Transport_Sendmail);
        }
    }
    function setTransport(Zend_Mail_Transport_Abstract $transport){
        $this->transport = $transport;
    }
    /**
     *
     * @return Zend_Mail_Transport_Abstract
     */
    function getTransport()
    {
        return $this->transport;
    }

    function _readConfig(){
        if (Am_Di::getInstance()->config->get('email_queue_enabled')) {
            $this->queueEnabled = true;
            $this->queuePeriod = Am_Di::getInstance()->config->get('email_queue_period', 3600);
            if ($this->queuePeriod <= 600){
                throw new Am_Exception_InternalError("email_queue_period set to invalid value [{$this->queuePeriod}]");
            }
            $this->queueLimit = Am_Di::getInstance()->config->get('email_queue_limit', 100);
            $this->queuePeriodicLimit = (int)$this->queueLimit * 80 / 100;
        }
        if (Am_Di::getInstance()->config->get('email_log_days') > 0) 
            $this->setLogDays(Am_Di::getInstance()->config->get('email_log_days', 7));
    }
    public function logEnabled(){
        return $this->logDays > 0;
    }
    public function setLogDays($days){
        $this->logDays = $days > 0 ? (int)$days : 0;
    }
    /**
     * Send message or put it queue if necessary
     */
    public function send(Zend_Mail $mail) 
    {
        if (!$mail instanceof Am_Mail) {
            trigger_error(__METHOD__ . ' should get Am_Mail, not Zend_Mail', E_USER_NOTICE);
            $isInstant = true;
        } else {
            $isInstant = $mail->isInstant();
        }
        $status = $this->getQueueStatus();
        $sent = null;
        $exception = null;
        if (in_array($status, array(self::QUEUE_DISABLED, self::QUEUE_OK))
            || (($status==self::QUEUE_ONLY_INSTANT) && $isInstant))
        {
            try {
                $this->transport->send($mail);
                $sent = Am_Di::getInstance()->time;
                // workaround for memory overusage
                if ($this->transport instanceof Zend_Mail_Transport_Smtp)
                    $this->transport->getConnection()->resetLog();
                // end of workaround
            } catch (Zend_Mail_Exception $e) {
                $exception = $e;
            }
        }
        if ($status != self::QUEUE_DISABLED || $this->logEnabled())
            $this->addToQueue($mail, $sent);
        if ($exception)
            throw $exception; // re-raise
    }
    /**
     * Put message to queue instead of sending it
     */
    protected function _sendMail() {
        if (defined('AM_FB_ENABLED')){
            fb(array('header'=>$this->header, 'body'=>$this->body), 'E-Mail');
        }
    }
    /**
     * Just save headers as it passed to
     * @param mixed $headers
     */
    protected function _prepareHeaders($headers) {
        $this->headers = $headers;
    }
    /**
     * Save e-mail to mail_queue table
     * @param Am_Mail $mail
     * @param int $sent timestamp
     * @return int inserted record id
     */
    public function addToQueue(Am_Mail $mail, $sent = null)
    {
        parent::send($mail);
        $vals = array(
            'from' => $mail->getFrom(),
            'recipients' => implode(',',$mail->getRecipients()),
            'count_recipients' =>  count($mail->getRecipients()),
            'subject' => $mail->getSubject(),
            'priority' => $mail->getPriority(),
            'body' => $this->body,
            'headers' => serialize($this->headers),
            'added' => Am_Di::getInstance()->time,
            'sent' => $sent ? $sent : null,
         );
        Am_Di::getInstance()->db->query("INSERT INTO ?_mail_queue SET ?a", $vals);
        return Am_Di::getInstance()->db->selectCell("SELECT LAST_INSERT_ID()");
    }
    /**
     * Send message to transport from queue
     * @param array $row as retreived from database
     */
    public function _sendSavedMessage(array & $row)
    {
        try {
            $ret = $this->transport->sendFromSaved($row['from'], $row['recipients'], preg_replace('/\r\n/', PHP_EOL, $row['body']), unserialize($row['headers']),
                $row['subject']);
            $row['sent'] = Am_Di::getInstance()->time;
            Am_Di::getInstance()->db->query("UPDATE ?_mail_queue SET sent=?d WHERE queue_id=?d",
                $row['sent'], $row['queue_id']);
        } catch (Zend_Mail_Exception $e) {
            Am_Di::getInstance()->errorLogTable->logException($e);
            $row['failures']++;
            if ($row['failures'] >= 3)
            { 
                //// deleting message on 3-rd failure
                Am_Di::getInstance()->db->query("DELETE FROM ?_mail_queue 
                    WHERE queue_id=?d", $row['queue_id']);
            } else {
                // save failure
                Am_Di::getInstance()->db->query("UPDATE ?_mail_queue 
                    SET failures=failures+1, last_error=?
                    WHERE queue_id=?d", $e->getMessage(), $row['queue_id']);
            }
        }
    }
    /**
     * Check if there are messages in queue, and sending is allowed,
     * then send
     */
    public function sendFromQueue(){
        if (!$this->queueEnabled) return;
        if ($this->getQueueStatus() != self::QUEUE_OK) return;
        //
        do{
            $sent = 0;
            $q = Am_Di::getInstance()->db->queryResultOnly(
               "SELECT * FROM ?_mail_queue
                WHERE sent IS NULL
                ORDER BY priority DESC, added, rand() limit 10"
            );
            while ($row = Am_Di::getInstance()->db->fetchRow($q))
            {
                if (!in_array($this->getQueueStatus(), array(self::QUEUE_OK, self::QUEUE_DISABLED))) 
                    return;
                $this->_sendSavedMessage($row);
                $sent++;
            }
        } while ($sent);
    }
    
    public function getQueueStatus(){
        if (!$this->queueEnabled) 
            return self::QUEUE_DISABLED;
        $sentLastPeriod = Am_Di::getInstance()->db->selectCell("SELECT SUM(count_recipients)
            FROM ?_mail_queue WHERE sent >= ?d",
            Am_Di::getInstance()->time - $this->queuePeriod);
        $this->leftMessagesToSend = max(0, $this->queuePeriodicLimit - $sentLastPeriod);
        if ($sentLastPeriod < $this->queuePeriodicLimit)
            return self::QUEUE_OK;
        elseif ($sentLastPeriod < $this->queueLimit)
            return self::QUEUE_ONLY_INSTANT;
        else
            return self::QUEUE_FULL;
    }
    /**
     * Remove old e-mail messages from the queue
     */
    function cleanUp()
    {
        $days = (int)Am_Di::getInstance()->config->get('email_log_days', 0);
        if (!$days) return; 
        Am_Di::getInstance()->db->query("DELETE FROM ?_mail_queue 
            WHERE added <= ?d", Am_Di::getInstance()->time - 3600*24*$days);
    }
}

/** 
 * @package Am_Mail
 * @internal
 */
class Am_Mime_Part extends Zend_Mime_Part {
    protected $_streamPath = null; // get this info for serialization
    function serialize(){
    }
    function unserialize(){
    }
}

/**
* Amazon Simple Email Service mail transport
*
* Integration between Zend Framework and Amazon Simple Email Service
*
* @package Am_Mail
* @license http://framework.zend.com/license/new-bsd New BSD License
* @author Alex Scott <alex@cgi-central.net>
* 
* Class is based on Chistopher Valles work Christopher Valles <info@christophervalles.com>
* https://github.com/christophervalles/Amazon-SES-Zend-Mail-Transport
* main change is usage of Am_HttpClient instead of Zend_Http_Client
*/
class Am_Mail_Transport_Ses extends Zend_Mail_Transport_Abstract
{

    /**
     * Template of the webservice body request
     *
     * @var string
     */
    protected $_bodyRequestTemplate = 'Action=SendRawEmail&Source=%s&%s&RawMessage.Data=%s';

    /**
     * Remote smtp hostname or i.p.
     *
     * @var string
     */
    protected $_host;

    /**
     * Amazon Access Key
     *
     * @var string|null
     */
    protected $_accessKey;

    /**
     * Amazon private key
     *
     * @var string|null
     */
    protected $_privateKey;

    /**
     * Constructor.
     *
     * @param string $endpoint (Default: https://email.us-east-1.amazonaws.com)
     * @param array|null $config (Default: null)
     * @return void
     * @throws Zend_Mail_Transport_Exception if accessKey is not present in the config
     * @throws Zend_Mail_Transport_Exception if privateKey is not present in the config
     */
    public function __construct(Array $config = array(), $host = 'https://email.us-east-1.amazonaws.com')
    {
        if (!array_key_exists('accessKey', $config))
        {
            throw new Zend_Mail_Transport_Exception('This transport requires the Amazon access key');
        }

        if (!array_key_exists('privateKey', $config))
        {
            throw new Zend_Mail_Transport_Exception('This transport requires the Amazon private key');
        }

        $this->_accessKey = $config['accessKey'];
        $this->_privateKey = $config['privateKey'];
        $this->_host = $host;
    }

    /**
     * Send an email using the amazon webservice api
     *
     * @return void
     */
    public function _sendMail()
    {
        $date = gmdate('D, d M Y H:i:s O');

        //Send the request
        $client = new Am_HttpRequest($this->_host, Am_HttpRequest::METHOD_POST);
        $client->setHeader(array(
            'Date' => $date,
            'X-Amzn-Authorization' => $this->_buildAuthKey($date)
        ));

        //Build the parameters
        $params = array(
            'Action' => 'SendRawEmail',
            'Source' => $this->_mail->getFrom(),
            'RawMessage.Data' => base64_encode(sprintf("%s\n%s\n", $this->header, $this->body))
        );

        $recipients = explode(',', $this->recipients);
        while (list($index, $recipient) = each($recipients))
        {
            $params[sprintf('Destination.ToAddresses.member.%d', $index + 1)] = $recipient;
        }

        $client->addPostParameter($params);
        $response = $client->send();

        if ($response->getStatus() != 200)
        {
            throw new Zend_Mail_Transport_Exception("Amazon SES: unexpected response: " . $response->getBody());
        }
    }

    /**
     * Format and fix headers
     *
     * Some SMTP servers do not strip BCC headers. Most clients do it themselves as do we.
     *
     * @access protected
     * @param array $headers
     * @return void
     * @throws Zend_Transport_Exception
     */
    protected function _prepareHeaders($headers)
    {
        if (!$this->_mail)
        {
            //require_once 'Zend/Mail/Transport/Exception.php';
            throw new Zend_Mail_Transport_Exception('_prepareHeaders requires a registered Zend_Mail object');
        }
        unset($headers['Bcc']);
        // Prepare headers
        parent::_prepareHeaders($headers);
    }

    /**
     * Returns header string containing encoded authentication key
     *
     * @param date $date
     * @return string
     */
    private function _buildAuthKey($date)
    {
        return sprintf('AWS3-HTTPS AWSAccessKeyId=%s,Algorithm=HmacSHA256,Signature=%s', $this->_accessKey, base64_encode(hash_hmac('sha256', $date, $this->_privateKey, TRUE)));
    }

    function sendFromSaved($from, $recipients, $body, array $headers, $subject){
        $this->_mail = new Am_Mail_Saved;
        $this->_mail->from = $from;
        $this->_mail->subject = $subject;
        $this->recipients = $recipients;
        $this->body = $body;
        $this->_prepareHeaders($headers);
        $this->_sendMail();
    }
}

Am_Mail::initDefaults();

/**
 * I believe using Zend_Validator_Hostname adds unacceptable overhead to Zend_Mail_Protocol
 * class, so it is disabled in this project
 * @internal
 * @package Am_Mail
 */
class Am_Util_Dummy_Zend_Validate 
{
    function addValidator(){}
    function isValid(){ return 1;}
}
