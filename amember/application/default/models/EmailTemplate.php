<?php
/**
 * Class represents records from table email_templates
 * {autogenerated}
 * @property int $email_template_id 
 * @property string $name 
 * @property string $lang 
 * @property string $format enum('text','html','multipart')
 * @property string $subject 
 * @property string $txt 
 * @property string $plain_txt 
 * @property string $attachments 
 * @property int $day 
 * @property string $not_conditions
 * @property bool $not_conditions_expired
 * @see Am_Table
 * 
 * @package Am_Mail_Template
 */
class EmailTemplate extends ResourceAbstract 
{
    const AUTORESPONDER  = 'autoresponder';
    const EXPIRE = 'expire';
    const PENDING_TO_USER = 'pending_to_user';
    const PENDING_TO_ADMIN = 'pending_to_admin';
    
    const ATTACHMENT_FILE_PREFIX = 'emailtemplate';
    const ATTACHMENT_AUTORESPONDER_EXPIRE_FILE_PREFIX = 'email-messages';
    const ATTACHMENT_PENDING_FILE_PREFIX = 'email-pending';
    
    const FORMAT_TEXT = 'text';
    const FORMAT_HTML = 'html';
    const FORMAT_MULTIPART = 'multipart';
    
    public function toRow() {
        $ret = parent::toRow();
        if (@$ret['day']=='') $ret['day'] = null;
        return $ret;
    }

    public function getLinkTitle()
    {
        return null;
    }
    /**
     * Check if user has no matches for $this->not_conditions
     * not conditions field format:
     *  comma delimited: c1 - category#1, p22 - product#22, c-1 - ANY PRODUCT
     * @param User $user
     * @return boolean true if ok (no matching conditions found)
     */
    public function checkNotConditions(User $user)
    {
        $pids = array();
        $catp = null;
        foreach (array_filter(explode(',', $this->not_conditions)) as $s)
        {
            if ($s == 'c-1') 
            {
                if (!$this->not_conditions_expired)
                    return $user->status != User::STATUS_ACTIVE;
                else
                    return $user->status == User::STATUS_PENDING;
            } elseif ($s[0] == 'p') {
                $pids[] = substr($s, 1);
            } elseif ($s[0] == 'c') {
                if (!$catp)
                    $catp = $this->getDi()->productCategoryTable->getCategoryProducts(true);
                if (!empty($catp[substr($s, 1)]))
                    $pids = array_merge($pids, $catp[substr($s, 1)]);
            }
        }
        if (!$pids) return true;
        $userPids = !$this->not_conditions_expired ? 
            $user->getActiveProductIds() :
            array_merge($user->getActiveProductIds(), $user->getExpiredProductIds());
        
        return !$pids || !array_intersect($pids, $userPids);
    }
}

/**
 * @package Am_Mail_Template
 */
class EmailTemplateTable extends ResourceAbstractTable
{
    protected $_key = 'email_template_id';
    protected $_table = '?_email_template';
    protected $_recordClass = 'EmailTemplate';
    public $_checkUnique = array(
        'name', 'lang', 'day'
    );
    
    // registry to do not send pending emails twice
    protected $_pendingSent = array();
    
    public function getAccessType()
    {
        return ResourceAccess::EMAILTEMPLATE;
    }
    public function getAccessTitle()
    {
        return ___('E-Mail Messages');
    }
    public function getPageId()
    {
        return 'emails';
    }
    public function getTitleField()
    {
        return 'name';
    }

    /**
     *
     * @param type $name
     * @param type $lang
     * @return EmailTemplate
     */
    public function findFirstExact($name, $lang = null)
    {
        $row = $this->getDi()->db->selectRow("SELECT * FROM ?_email_template
            WHERE name=?
            {ORDER BY lang=? DESC, lang='en' DESC}",
                $name,
                is_null($lang) ? DBSIMPLE_SKIP : $lang);
        return $row ? $this->createRecord($row) : null;
    }
    
    // --------- ------------- -------------- -------------- ---------------/
    function deleteByFields($name,$day=null){
        $this->_db->query("DELETE FROM ?_email_template
            WHERE name=?  {AND day=?}",
                $name,
                $day!="" ? $day : DBSIMPLE_SKIP
                );
    }
    /**
     * Find exact template by criteria
     * @return EmailTemplate
     */
    function getExact($name, $lang=null, $day=null)
    {
        $row = $this->getDi()->db->selectRow("SELECT * FROM ?_email_template
            WHERE name=?
            {AND lang=?} {AND day=?}",
                $name,
                is_null($lang) ? DBSIMPLE_SKIP : $lang,
                is_null($day) ? DBSIMPLE_SKIP : $day);
        return $row ? $this->createRecord($row) : null;
    }

    /**
     * Search available days options by criteria
     * @return array of int (available days)
     */
    function getDays($name, $exclude=null){
        return $this->getDi()->db->selectCol("SELECT DISTINCT day
            FROM ?_email_template
            WHERE name=? AND day IS NOT NULL
            {AND day<>?}
            ORDER BY day",
                $name,
                is_null($exclude) ? 0 : $exclude
            );
    }

    public function getLanguages($name, $day=null, $exclude=null){
        return $this->_db->selectCol("SELECT DISTINCT lang as ARRAY_KEY, lang
            FROM ?_email_template
            WHERE name=? {AND day=?} {AND lang<>?}",
                $name, 
                is_null($day) ? DBSIMPLE_SKIP : $day,
                is_null($exclude) ? DBSIMPLE_SKIP : $exclude
            );
    }

    
    /**
     * @link Invoice->start
     */
    public function sendZeroAutoresponders(User $user)
    {
        foreach ($this->getDi()->resourceAccessTable->
            getAllowedResources($user, ResourceAccess::EMAILTEMPLATE) 
                as $et)
        {
            if (($et->name != EmailTemplate::AUTORESPONDER) || ($et->day != 1)) continue;
            
            // check if no matching not_conditions
            if (!$et->checkNotConditions($user)) continue;
            
            // don't send same e-mail twice
            $sent = (array)$user->data()->get('zero-autoresponder-sent');
            if (in_array($et->pk(), $sent)) continue;
            $sent[] = $et->pk();
            ///
            $tpl = Am_Mail_Template::createFromEmailTemplate($et);
            $tpl->user = $user;
            $tpl->send($user);
            // store sent emails
            $user->data()->set('zero-autoresponder-sent', $sent)->update();
        }
    }
    
    static function onInvoiceStarted(Am_Event_InvoiceStarted $event)
    {
        $invoice = $event->getInvoice();
        $event->getDi()->emailTemplateTable->sendZeroAutoresponders($invoice->getUser());
        if ($invoice->first_total > 0 || $invoice->second_total > 0) return;
        if ($event->getDi()->config->get('send_free_payment_admin'))
        {
            $et = Am_Mail_Template::load('send_free_payment_admin', $event->getUser()->lang);
            if ($et)
            {
                $et->setUser($event->getUser())
                   ->setInvoice($invoice)
                   ->setPayment($event->getPayment())
                   ->setInvoice_text($invoice->render());
                $et->send(Am_Mail_Template::TO_ADMIN);
            }
        }        
    }
    
    static function onPaymentWithAccessAfterInsert(Am_Event_PaymentWithAccessAfterInsert $event)
    {
        /**
         * This e-mail is sent for first payment in invoice only
         * another template must be used for the following payments
         */
        // if ($event->getInvoice()->getPaymentsCount() != 1) return;
        if ($event->getDi()->config->get('send_payment_mail'))
        {
            $et = Am_Mail_Template::load('send_payment_mail', $event->getUser()->lang);
            if ($et && (($event->getPayment()->amount > 0)))
            {
                $et->setUser($event->getUser())
                   ->setInvoice($event->getInvoice())
                   ->setPayment($event->getPayment())
                   ->setInvoice_text($event->getInvoice()->render('', $event->getPayment()));
                
                if (Am_Di::getInstance()->config->get('send_pdf_invoice', false)) {
                    try{
                        $invoice = new Am_Pdf_Invoice($event->getPayment());
                        $invoice->setDi(Am_Di::getInstance());
                        $et->getMail()->createAttachment(
                                    $invoice->render(), 
                                    'application/pdf', 
                                    Zend_Mime::DISPOSITION_ATTACHMENT, 
                                    Zend_Mime::ENCODING_BASE64, 
                                    $invoice->getFileName()
                                );
                    }
                    catch(Exception $e)
                    {
                        Am_Di::getInstance()->errorLogTable->logException($e);
                    }
                }
                
                $et->send($event->getUser());
            }
        }
        
        if ($event->getDi()->config->get('send_payment_admin'))
        {
            $et = Am_Mail_Template::load('send_payment_admin', $event->getUser()->lang);
            if ($et && (($event->getPayment()->amount > 0)))
            {
                $et->setUser($event->getUser())
                   ->setInvoice($event->getInvoice())
                   ->setPayment($event->getPayment())
                   ->setInvoice_text($event->getInvoice()->render('', $event->getPayment()));

                if (Am_Di::getInstance()->config->get('send_pdf_invoice', false)) {
                    try{
                        $invoice = new Am_Pdf_Invoice($event->getPayment());
                        $invoice->setDi(Am_Di::getInstance());
                        $et->getMail()->createAttachment(
                                    $invoice->render(),
                                    'application/pdf',
                                    Zend_Mime::DISPOSITION_ATTACHMENT,
                                    Zend_Mime::ENCODING_BASE64,
                                    $invoice->getFileName()
                                );
                    }
                    catch(Exception $e)
                    {
                        Am_Di::getInstance()->errorLogTable->logException($e);
                    }
                }

                $et->send(Am_Mail_Template::TO_ADMIN);
            }
        }
    }

    /* for tests */
    public function ___isNotificationRuleMatch(EmailTemplate $tmpl, Invoice $invoice)
    {
        return $this->isNotificationRuleMatch($tmpl, $invoice);
    }

    protected function isNotificationRuleMatch(EmailTemplate $tmpl, Invoice $invoice)
    {
        if (!$tmpl->conditions) return true;

        $conds = array();
        foreach(explode(',', $tmpl->conditions) as $item)
        {
            preg_match('/([A-Z]*)-(.*)/', $item, $match);
            $conds[$match[1]][] = $match[2];
        }

        //check product conditions
        $product_ids = array();

        if (isset($conds['CATEGORY'])) {
            $catProducts = $this->getDi()->productCategoryTable->getCategoryProducts();
            foreach ($conds['CATEGORY'] as $cat_id) {
                if (isset($catProducts[$cat_id]))
                    $product_ids = array_merge($product_ids, $catProducts[$cat_id]);
            }
        }

        if (isset($conds['PRODUCT'])) {
            $product_ids = array_merge($product_ids, $conds['PRODUCT']);
        }

        if (!count($product_ids) && isset($conds['CATEGORY'])) {
            //user set up categories without products
            return false;
        }

        if (count($product_ids)) {
            $invoice_product_id = array_map(create_function('$a', 'return $a->pk();'), $invoice->getProducts());
            if (!array_intersect($product_ids, $invoice_product_id))
                return false;
        }

        //check paysystem conditions
        if (isset($conds['PAYSYSTEM']) && !in_array($invoice->paysys_id, $conds['PAYSYSTEM']))
            return false;

        return true;

    }

    /**
     *
     * @param string $name
     * @return array array day => array(templates for day)
     */
    protected function findPendingNotificationRules($name)
    {
        $templates = $this->findByName($name);
        $days = array();
        foreach ($templates as $tpl) {
            $dd = array_filter(explode(',', $tpl->days), create_function('$a', 'return $a!=="";'));
            foreach ($dd as $d) {
                $days[$d][] = $tpl;
            }
        }
        return $days;
    }

    protected function sendPendingNotifications($day, $invoices, $tpls, $sendCallback)
    {
        foreach ($invoices as $invoice) {
            $user = $invoice->getUser();
            if (!$user) continue;
            foreach ($tpls as $tpl) {
                if ($this->isNotificationRuleMatch($tpl, $invoice)) {
                    if (!empty($this->_pendingSent[$sendCallback[1]][$invoice->getUser()->pk()]))
                        continue;
                    $mailTpl = Am_Mail_Template::createFromEmailTemplate($tpl);
                    $mailTpl->setUser($invoice->getUser());
                    $mailTpl->setInvoice($invoice);
                    $mailTpl->setDay($day);

                    $products = array();
                    foreach ($invoice->getProducts() as $product)
                        $products[] = $product->getTitle();
                    $mailTpl->setProduct_title(implode (", ", $products));
                    
                    call_user_func($sendCallback, $mailTpl, $invoice);
                    $this->_pendingSent[$sendCallback[1]][$invoice->getUser()->pk()] = true;
                    break;
                }
            }
        }
    }

    protected function sendPendingNotificationToUser(Am_Mail_Template $mailTpl, Invoice $invoice)
    {
        $user = $invoice->getUser();
        if ($user->unsubscribed) return;
        $mailTpl->send($user);
    }

    protected function sendPendingNotificationToAdmin(Am_Mail_Template $mailTpl, Invoice $invoice)
    {
        $mailTpl->sendAdmin();
    }

    protected function _sendCronPendingNotifications($name, $sendCallback)
    {
          $days = $this->findPendingNotificationRules($name);
          unset($days[0]); //it should been send on invoice created

          foreach ($days as $d => $tpls) {
              $date = $this->getDi()->dateTime;
              $date->modify('-' . $d . ' days');
              $begin_date = $date->format('Y-m-d 00:00:00');
              $end_date = $date->format('Y-m-d 23:59:59');

              $query = new Am_Query($this->getDi()->invoiceTable);
              $query = $query->addWhere('status=?', Invoice::PENDING)
                  ->addWhere('tm_added>?', $begin_date)
                  ->addWhere('tm_added<?', $end_date);
              $t = $query->getAlias();
              $query->addWhere("NOT EXISTS
                  (SELECT * FROM ?_invoice_payment ip
                   WHERE ip.user_id = $t.user_id 
                     AND ip.dattm>=?
                     AND ip.dattm<GREATEST(?, $t.tm_added + INTERVAL 48 HOUR)
                         LIMIT 1
                 )", $begin_date, $end_date);
              if ($count = $query->getFoundRows()) {
                  $invoices = $query->selectPageRecords(0, $count);
                  $this->sendPendingNotifications($d, $invoices, $tpls, $sendCallback);
              }
          }
    }

    protected function _sendZeroPendingNotifications($name, $sendCallback, Invoice $invoice)
    {
        $days = $this->findPendingNotificationRules($name);
        if (isset($days[0])) {
            $tpls = $days[0];
            $this->sendPendingNotifications(0, array($invoice), $tpls, $sendCallback);
        }
    }

    public function sendCronPendingNotifications()
    {
          $this->_sendCronPendingNotifications('pending_to_user', array($this, 'sendPendingNotificationToUser'));
          $this->_sendCronPendingNotifications('pending_to_admin', array($this, 'sendPendingNotificationToAdmin'));
    }

    public function onInvoiceAfterInsert(Am_Event $event)
    {
        $invoice = $event->getInvoice();
        if ($invoice->data()->get('added-by-admin')) 
            return; // do not send automatic e-mails if invoice added by admin
        $this->_sendZeroPendingNotifications('pending_to_user', array($this, 'sendPendingNotificationToUser'), $invoice);
        $this->_sendZeroPendingNotifications('pending_to_admin', array($this, 'sendPendingNotificationToAdmin'), $invoice);
    }
    
    public function sendCronAutoresponders()
    {
        $userTable = $this->getDi()->userTable;
        $etCache = array();
        $db = $this->getDi()->db;
        $q = $this->getDi()->resourceAccessTable->getResourcesForMembers(ResourceAccess::EMAILTEMPLATE)->query();
        while ($res = $db->fetchRow($q))
        {
            $user = $userTable->load($res['user_id'], false);
            if ($user->unsubscribed) continue;
            if (!$user) continue; // no user found
            
            if (!array_key_exists($res['resource_id'], $etCache))
                $etCache[$res['resource_id']] = $this->load($res['resource_id'], false);
            
            if (!$etCache[$res['resource_id']]) continue; // no template found

            //do not send zero autoresponder second time
            if($etCache[$res['resource_id']]->day==1)
                if (in_array($res['resource_id'], (array)$user->data()->get('zero-autoresponder-sent'))) continue;

            if (($etCache[$res['resource_id']]->name != EmailTemplate::AUTORESPONDER)) continue;
            
            // check if no matching not_conditions
            if (!$etCache[$res['resource_id']]->checkNotConditions($user)) continue;
            
            $tpl = Am_Mail_Template::createFromEmailTemplate($etCache[$res['resource_id']]);
            $tpl->user = $user;
            $tpl->send($user);
        }
    }
    
    public function sendCronExpires()
    {
        $mails = $this->findBy(array('name' => EmailTemplate::EXPIRE));
        if (!$mails) return; // nothing to send
        $byDate = array(); // templates by expiration date
        foreach ($mails as $et)
        {
            $et->_productIds = $et->findMatchingProductIds();
            ///
            $day = - $et->day;
            $string = $day . ' days';
            if ($day >= 0) $string = "+$string";
            if ($day == 0) $string = 'today';
            $date = date('Y-m-d', strtotime($string));
            
            $byDate[$date][] = $et;
        }
        
        $userTable = $this->getDi()->userTable;
        // now query expirations
        $q = $this->getDi()->accessTable->queryExpirations(array_keys($byDate));
        $sent = array(); // user_id => array('tpl_id')
        while ($row = $this->_db->fetchRow($q))
        {
            $user = $userTable->createRecord($row);
            if ($user->unsubscribed) continue;
            foreach ($byDate[$row['_expire_date']] as $et)
            {
                // do not send same template agian to the same user
                if (array_search($et->pk(), (array)@$sent[$user->user_id]) !== false) continue; 
                
                
                if ($et->_productIds == ResourceAccess::ANY_PRODUCT ||
                    (in_array($row['_product_id'], $et->_productIds))) 
                {
                    // check if no matching not_conditions
                    if (!$et->checkNotConditions($user)) continue;
                    
                    $tpl = Am_Mail_Template::createFromEmailTemplate($et);
                    $tpl->user = $user;
                    $tpl->expires = amDate($row['_expire_date']);
                    $tpl->send($user);
                    $sent[$user->user_id][] = $et->pk();
                }
            }
        }
    }
}
