<?php

class AdminLogsController extends Am_Controller_Pages
{
    public function initPages()
    {
        $admin = $this->getDi()->authAdmin->getUser();

        if ($admin->hasPermission(Am_Auth_Admin::PERM_LOGS))
            $this->addPage(array($this,'createErrors'), 'errors', ___('Errors'));

        if ($admin->hasPermission(Am_Auth_Admin::PERM_LOGS_ACCESS))
             $this->addPage(array($this, 'createAccess'), 'access', ___('Access'));

        if ($admin->hasPermission(Am_Auth_Admin::PERM_LOGS_INVOICE))
             $this->addPage(array($this, 'createInvoice'), 'invoice', ___('Invoice'));

        if ($admin->hasPermission(Am_Auth_Admin::PERM_LOGS_MAIL))
             $this->addPage(array($this, 'createMailQueue'), 'mailqueue', ___('Mail Queue'));

        if ($admin->hasPermission(Am_Auth_Admin::PERM_LOGS_ADMIN))
            $this->addPage(array($this, 'createAdminLog'), 'adminlog', ___('Admin Log'));
    }
    /// 
    public function checkAdminPermissions(Admin $admin)
    {
        foreach(array(
            Am_Auth_Admin::PERM_LOGS,
            Am_Auth_Admin::PERM_LOGS_ACCESS,
            Am_Auth_Admin::PERM_LOGS_INVOICE,
            Am_Auth_Admin::PERM_LOGS_MAIL,
            Am_Auth_Admin::PERM_LOGS_ADMIN
            ) as $perm) {
            if ($admin->hasPermission($perm)) return true;
        }

        return false;
    }
    public function createErrors()
    {
        $q = new Am_Query($this->getDi()->errorLogTable);
        $q->setOrder('time', 'desc');
        $g = new Am_Grid_ReadOnly('_error', ___('Error/Debug Log'), $q, $this->getRequest(), $this->view);
        $g->setPermissionId(Am_Auth_Admin::PERM_LOGS);
        $g->addField(new Am_Grid_Field_Date('time', ___('Time'), true, '', null, '10%'));
        $g->addField(new Am_Grid_Field_Expandable('url', ___('URL'), true, '', null, '20%'))
            ->setPlaceholder(Am_Grid_Field_Expandable::PLACEHOLDER_SELF_TRUNCATE_BEGIN)
            ->setMaxLength(25);
        $g->addField(new Am_Grid_Field('remote_addr', ___('IP'), true, '', null, '10%'));
        $g->addField(new Am_Grid_Field('error', ___('Message'), true, '', null, '45%'));
        $f = $g->addField(new Am_Grid_Field_Expandable('trace', ___('Trace'), false, '', null, '15%'))
             ->setGetFunction(array($this, 'escapeTrace'));
        $f->setEscape(false);
        $g->setFilter(new Am_Grid_Filter_Text(___('Filter'), array(
            'url' => 'LIKE', 
            'remote_addr' => 'LIKE',
            'referrer' => 'LIKE',
            'error' => 'LIKE',
        )));
        return $g;
    }
    public function escapeTrace(ErrorLog $l)
    {
        return highlight_string($l->trace, true);
    }
    public function createAccess()
    {
        $query = new Am_Query($this->getDi()->accessLogTable);
        $query->leftJoin('?_user', 'm', 't.user_id=m.user_id')
            ->addField("m.login", 'member_login')
            ->addField("CONCAT(m.name_f, ' ', m.name_l)", 'member_name');        
        $query->setOrder('time', 'desc');
        $g = new Am_Grid_ReadOnly('_access', ___('Access Log'), $query, $this->getRequest(), $this->view);
        $g->setPermissionId(Am_Auth_Admin::PERM_LOGS_ACCESS);
        $g->addGridField(new Am_Grid_Field_Date('time', ___('Time'), true, '', null, '10%'));
        $g->addGridField(new Am_Grid_Field('member_login', ___('User'), true, '', array($this, 'renderAccessMember'), '10%'));
        $g->addGridField(new Am_Grid_Field_Expandable('url', ___('URL'), true, '', null, '20%'))
            ->setPlaceholder(Am_Grid_Field_Expandable::PLACEHOLDER_SELF_TRUNCATE_BEGIN)
            ->setMaxLength(25);
        $g->addGridField(new Am_Grid_Field('remote_addr', ___('IP'), true, '', null, '10%'));
        $g->addGridField(new Am_Grid_Field_Expandable('referrer', ___('Referrer'), true, '', null, '15%'))
            ->setPlaceholder(Am_Grid_Field_Expandable::PLACEHOLDER_SELF_TRUNCATE_BEGIN)
            ->setMaxLength(25);;
        $g->setFilter(new Am_Grid_Filter_Text(___('Filter by IP or Referrer or URL'), array(
            'remote_addr' => 'LIKE',
            'referrer' => 'LIKE', 
            'url' => 'LIKE',
        )));
        return $g;
    }
    public function createInvoice()
    {
        $query  = new Am_Query(new InvoiceLogTable);
        $query->addField("m.login", "login");
        $query->addField("m.user_id", "user_id");
        $query->leftJoin("?_user", "m", "t.user_id=m.user_id");
        $query->setOrder('tm', 'desc');
        $g = new Am_Grid_Editable('_invoice', ___('Invoice Log'), $query, $this->getRequest(), $this->view);
        $g->setPermissionId(Am_Auth_Admin::PERM_LOGS_INVOICE);
        $g->addField(new Am_Grid_Field('tm', ___('Time'), true, '', array($this, 'renderTimestamp'), '10%'));
        $g->addField(new Am_Grid_Field('invoice_id', ___('Invoice'), true, '', array($this, 'renderInvoice'), '5%'));
        $g->addField(new Am_Grid_Field('login', ___('User'), true, '', array($this, 'renderUser'), '5%'));
        $g->addField(new Am_Grid_Field('remote_addr', ___('IP'), true, '', null, '5%'));
        $g->addField(new Am_Grid_Field('paysys_id', ___('Paysystem'), true, '', null, '10%'));
        $g->addField(new Am_Grid_Field('title', ___('Title'), true, '', null, '25%'));
        $g->addField(new Am_Grid_Field_Expandable('details', ___('Details'), false, '', null, '25%'))
            ->setGetFunction(array($this, 'renderInvoiceDetails'));
        $g->actionsClear();
        $g->actionAdd(new Am_Grid_Action_InvoiceRetry('retry'));
        $g->setFilter(new Am_Grid_Filter_InvoiceLog);
        $g->actionAdd(new Am_Grid_Action_Group_Callback('retrygroup', ___("Repeat Action Handling"), array('Am_Grid_Action_InvoiceRetry', 'groupCallback')));
        $g->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, array($this, 'getTrAttribs'));
        return $g;
    }

    public function getTrAttribs(& $ret, $record)
    {
        if ($record->is_processed)
        {
            $ret['class'] = isset($ret['class']) ? $ret['class'] . ' disabled' : 'disabled';
        }
    }

    public function renderInvoice($record){
        return sprintf('<td><a target="_top" href="%s">%s</a></td>', 
            $this->escape(REL_ROOT_URL . "/admin-user-payments/index/user_id/".$record->user_id."#invoice-".$record->invoice_id), 
            $record->invoice_id);
        
    }
    public function renderUser($record){
        return sprintf('<td><a target="_top" href="%s">%s</a></td>', 
            $this->getView()->userUrl($record->user_id), $record->login);
        
    }
    public function renderAccessMember($record)
    {
        return sprintf('<td><a target="_top" href="%s">%s (%s)</a></td>', 
            $this->getView()->userUrl($record->user_id), $record->member_login, $record->member_name);
    }
    public function renderTime($record)
    {
        return amDateTime($record->time);
    }
    
    public function renderAdminTime($record)
    {
        return sprintf("<td>%s</td>", amDateTime($record->dattm));
    }
    
    public function renderAdmin($record)
    {
        return sprintf('<td><a href="%s" target="_top">%s</a></td>', 
            $this->escape(REL_ROOT_URL . "/admin-admins?_admin_a=edit&_admin_id=" . (int)$record->admin_id),
            $this->escape($record->admin_login)
        );
    }
    
    public function renderRec(AdminLog $record)
    {
        $text = "";
        if ($record->tablename || $record->record_id)
            $text = $this->escape($record->tablename . ":" . $record->record_id);
        // @todo - add links here to edit pages
        return sprintf('<td>%s</td>', $text);
    }
    
    public function renderInvoiceDetails(InvoiceLog $obj, $field, $controller, $fieldObj)
    {
        $fieldObj->setEscape(true);
        $ret = "";
        $ret .= "<div class='collapsible'>\n";
        $rows = $obj->getRenderedDetails();
        $open = count($rows) == 1 ? 'open' : '';
        foreach ($rows as $row)
        {
            $popup = @$row[2];
            if ($popup) $popup = "<br /><br />ENCODED DETAILS:<br />" . nl2br($row[2]);
            $ret .= "\t<div class='item $open'>\n";
            $ret .= "\t\t<div class='head'>$row[0]</div>\n";
            $ret .= "\t\t<div class='more'>$row[1]$popup</div>\n";
            $ret .= "\t</div>\n";
        }
        $ret .= "</div>\n\n";
        return $ret;
    }
    public function createMailQueue()
    {
        $ds = new Am_Query($this->getDi()->mailQueueTable);
        $ds->setOrder('added', true);
        
        $g = new Am_Grid_Editable('_mail', ___("E-Mail Queue"), $ds, $this->getRequest(), $this->view);
        $g->setPermissionId(Am_Auth_Admin::PERM_LOGS_MAIL);
        $g->addGridField(new Am_Grid_Field('recipients', ___('Recipients'), true, '', null, '20%'));
        $g->addGridField(new Am_Grid_Field('added', ___('Added'), true, '', array($this, 'renderTimestamp'), '15%'));
        $g->addGridField(new Am_Grid_Field('sent', ___('Sent'), true, '', array($this, 'renderTimestamp'), '15%'));
        $g->addGridField(new Am_Grid_Field('subject', ___('Subject'), true, '', null, '30%'))
            ->setRenderFunction(array($this, 'renderSubject'));
        
        $body = new Am_Grid_Field_Expandable('body', ___('Mail'), true, '', null, '20%');
        $body->setEscape(true);
        $body->setGetFunction(array($this, 'renderMail'));
        $g->addGridField($body);
        
        $g->setFilter(new Am_Grid_Filter_Text(___("Filter by subject or recepient"), array(
            'subject' => 'LIKE',
            'recipients' => 'LIKE',
        )));
        $g->actionsClear();
        $g->actionAdd(new Am_Grid_Action_MailRetry('retry'));

        return $g;
    }
    
    function renderMail($obj, $controller, $field, Am_Grid_Field_Expandable $fieldObj)
    {
        $_body = $obj->body;
        $_headers = unserialize($obj->headers);
        $atRendered = null;

        $val = '';
        $headers = array();
        foreach ($_headers as $k => $v)
        {
            $headers[$k] = $v[0];
        }

        if (isset($headers['Content-Transfer-Encoding']) &&
            $headers['Content-Transfer-Encoding'] == 'quoted-printable')
        {
            $body = quoted_printable_decode($_body);
            if (strpos($headers['Subject'], '=?') === 0)
                $headers['Subject'] = mb_decode_mimeheader($headers['Subject']);
        } else
        {
            $body = base64_decode($_body);
        }
        if ($body) $body = nl2br($body);

        foreach ($headers as $headerName => $headerVal)
        {
            $val .= '<b>' . $headerName . '</b> : <i>' . Am_Controller::escape($headerVal) . '</i><br />';
        }

        if (isset($headers['Content-Type']) &&
            strstr($headers['Content-Type'], 'multipart/mixed'))
        {

            preg_match('/boundary="(.*)"/', $headers['Content-Type'], $matches);
            $boundary = $matches[1];

            $message = @Zend_Mime_Message::createFromMessage($body, $boundary);
            $parts = $message->getParts();
            $part = @$parts[0];
            if ($part)
            {
                $body = $part->getContent();
                if ($part->encoding == 'quoted-printable')
                {
                    $body = quoted_printable_decode($body);
                } else
                {
                    $body = base64_decode($body);
                }
                }
            $attachments = array_slice($parts, 1);
            $atRendered = '';
            foreach ($attachments as $at)
            {
                preg_match('/filename="(.*)"/', $at->disposition, $matches);
                $filename = @$matches[1];
                $atRendered .= sprintf("&mdash %s (%s)", $filename, $at->type) . '<br />';
            }
        }
        $val .= '<br />' . $body . ($atRendered ? '<br /><strong>Attachments:</strong><br />' . $atRendered : '');
        return $val;
    }

    function renderSubject(MailQueue $m)
    {
        $s = $m->subject;
        if (strpos($s, '=?') === 0)
            $s = mb_decode_mimeheader($s);
        return "<td>". Am_Controller::escape($s) . "</td>";
    }

    function renderTimestamp($record, $field)
    {
        $val = $record->$field;
        if ($val)
        {
            $val = amDatetime($val);
        } else
        {
            $val = 'Not Sent';
        }
        return sprintf('<td>%s</td>', $val);
    }

    public function createAdminLog()
    {
        $ds = new Am_Query($this->getDi()->adminLogTable);
        $ds->setOrder('dattm', 'desc');
        
        $g = new Am_Grid_ReadOnly('_admin', ___("Admin Log"), $ds, $this->getRequest(), $this->view);
        $g->setPermissionId(Am_Auth_Admin::PERM_LOGS_ADMIN);
        $g->addGridField(new Am_Grid_Field('dattm', ___('Time'), true, '', array($this, 'renderAdminTime'), '10%'));
        $g->addGridField(new Am_Grid_Field('admin_login', ___('Admin'), true, '', array($this, 'renderAdmin'), '10%'));
        $g->addGridField(new Am_Grid_Field('ip', ___('IP'), true, '', null, '10%'));
        $g->addGridField(new Am_Grid_Field('message', ___('Message')));
        $g->addGridField(new Am_Grid_Field('record', ___('Record')))->setRenderFunction(array($this, 'renderRec'));
        
        $g->setFilter(new Am_Grid_Filter_AdminLog);
        return $g;
    }
}

class Am_Grid_Filter_InvoiceLog extends Am_Grid_Filter_Abstract
{
    public function __construct()
    {
        $this->title = ___("Filter by string or by invoice#/member#");
    }
    protected function applyFilter()
    {
        $query = $this->grid->getDataSource();
        $filter = $this->vars['filter'];
        $condition = $query->add(new Am_Query_Condition_Field('paysys_id', 'LIKE', '%' . $filter . '%'))
            ->_or(new Am_Query_Condition_Field('title', 'LIKE', '%' . $filter . '%'))
            ->_or(new Am_Query_Condition_Field('type', 'LIKE', '%' . $filter . '%'))
            ->_or(new Am_Query_Condition_Field('details', 'LIKE', '%' . $filter . '%'));
        if ($filter > 0)
        {
            $condition->_or(new Am_Query_Condition_Field('invoice_id', '=', (int)$filter));
            $condition->_or(new Am_Query_Condition_Field('user_id', '=', (int)$filter));
        }
    }
    public function renderInputs()
    {
        return $this->renderInputText();
    }
}

class Am_Grid_Filter_AdminLog extends Am_Grid_Filter_Abstract
{
    public function __construct()
    {
        $this->title = ___("Filter by record_id or by message");
    }
    protected function applyFilter()
    {
        $query = $this->grid->getDataSource();
        $filter = $this->vars['filter'];
        $condition = $query->add(new Am_Query_Condition_Field('message', 'LIKE', '%' . $filter . '%'))
            ->_or(new Am_Query_Condition_Field('record_id', 'LIKE', '%' . $filter . '%'));
    }
    public function renderInputs()
    {
        return $this->renderInputText();
    }
}

class Am_Grid_Action_InvoiceRetry extends Am_Grid_Action_Abstract
{
    protected $type = self::SINGLE;
    
    public function __construct($id = null, $title = null)
    {
        $this->title = ___("Repeat Action Handling");
        parent::__construct($id, $title);
        $this->setTarget('_top');
    }
    
    public function isAvailable($record)
    {
        return (strpos($record->details, 'type="incoming-request"') !== false);
    }

    public static function repeat(InvoiceLog $invoiceLog, array & $response)
    {
        Am_Di::getInstance()->plugins_payment->load($invoiceLog->paysys_id);
        $paymentPlugin = Am_Di::getInstance()->plugins_payment->get($invoiceLog->paysys_id);
        $paymentPlugin->toggleDisablePostbackLog(true);
        /* @var $paymentPlugin Am_Paysystem_Abstract */
        try
        {
            $request = $invoiceLog->getFirstRequest();
            if (!$request instanceof Am_Request)
                throw new Am_Exception_InputError("Am_Request is not saved for this record, this action cannot be repeated");
            $resp = new Zend_Controller_Response_Http();

            Zend_Controller_Front::getInstance()->getRouter()->route($request);

            $paymentPlugin->toggleDisablePostbackLog(true);
            $paymentPlugin->directAction($request, $resp, array('di' => Am_Di::getInstance()));

            $response['status'] = 'OK';
            $response['msg'] = 'The action has been repeated, ipn script response [' . $resp->getBody() . "]";
        } catch (Exception $e)
        {
            $response['status'] = 'ERROR';
            $response['msg'] = sprintf("Exception %s : %s", get_class($e), $e->getMessage());
        }
    }
    
    
    public function run()
    {
        echo $this->renderTitle();
        $invoiceLog = Am_Di::getInstance()->invoiceLogTable->load($this->getRecordId());

        $response = array();
        try
        {
            self::repeat($invoiceLog, $response);
        } catch (Exception $e)
        {
            $response['status'] = 'ERROR';
            $response['msg'] = $e->getMessage();
        }

        echo "<b>RESULT: $response[status]</b><br />";
        echo $response['msg'];
        echo "<br /><br />\n";
        echo $this->renderBackUrl();
    }
    
    static function groupCallback($id, InvoiceLog $record, Am_Grid_Action_Group_Callback $action, Am_Grid_Editable $grid)
    {
        @set_time_limit(3600);
        try {
            $req = $record->getFirstRequest();
            if (!$req) 
            {
                echo "<br />\n$record->log_id: SKIPPED";
                return;
            }
            $response = array();
            self::repeat($record, $response);
        } catch (Exception $e) {
            $response['status'] = 'Error';
            $response['msg'] = $e->getMessage();
        }
        echo "<br />\n$record->log_id: {$response['status']} : {$response['msg']}";
    }
}

class Am_Grid_Action_MailRetry extends Am_Grid_Action_Abstract
{
    protected $type = self::SINGLE;

    public function __construct($id = null, $title = null)
    {
        $this->title = ___("Resend Email");
        parent::__construct($id, $title);
        $this->setTarget('_top');
    }

    public function isAvailable($record)
    {
        return !$record->sent;
    }


    public function run()
    {
        echo $this->renderTitle();
        $record = Am_Di::getInstance()->mailQueueTable->load($this->getRecordId());
        $row = $record->toArray();

        $response = array();
        try
        {
            Am_Mail_Queue::getInstance()->getTransport()
                ->sendFromSaved($row['from'], $row['recipients'],
                    $row['body'], unserialize($row['headers']), $row['subject']);
            $row['sent'] = Am_Di::getInstance()->time;
            Am_Di::getInstance()->db->query("UPDATE ?_mail_queue SET sent=?d WHERE queue_id=?d",
                $row['sent'], $row['queue_id']);

            $response['status'] = 'OK';
            $response['msg'] = 'Email has been send';

        } catch (Exception $e) {
            $response['status'] = 'ERROR';
            $response['msg'] = $e->getMessage();
        }

        echo "<b>RESULT: $response[status]</b><br />";
        echo $response['msg'];
        echo "<br /><br />\n";
        echo $this->renderBackUrl();
    }
}




