<?php

/*
 *
 *     Author: Alex Scott
 *      Email: alex@cgi-central.net
 *        Web: http://www.cgi-central.net
 *    Details: Admin Info / PHP
 *    FileName $RCSfile$
 *    Release: 4.2.17 ($Revision$)
 *
 * Please direct bug reports,suggestions or feedback to the cgi-central forums.
 * http://www.cgi-central.net/forum/
 *
 * aMember PRO is a commercial software. Any distribution is strictly prohibited.
 *
 *
 * @todo check with utf-8
 * @todo check queue
 * @todo check parallel working (locking?)
 *
 */

class AdminEmailController extends Am_Controller
{

    /** @var Am_Query_Ui */
    protected $searchUi ;

    protected $_attachments = array();
    protected $queue_id;
    /** @var EmailSent */
    protected $saved;
    
    protected $form;
    protected $tagOptions;
    
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_EMAIL);
    }

    function renderUserUrl(User $user)
    {
        $url = $this->getView()->userUrl($user->user_id);
        return sprintf('<td><a href="%s" target="_blank">%s</a></td>',
            $this->escape($url), $this->escape($user->login));
    }
    function browseUsersAction()
    {
        $withWrap = (bool)$this->_request->get('_u_wrap');
        unset($_GET['_u_wrap']);
        
        $ds = $this->searchUi->getActive()->getQuery();
        $grid = new Am_Grid_ReadOnly('_u', ___("Selected for E-Mailing"), $ds,
            $this->_request, $this->view);
        if ($withWrap) $grid->isAjax(false);
        $grid->setCountPerPage(10);
        $grid->addField("login", ___("Username"))->setRenderFunction(array($this, 'renderUserUrl'));
        $grid->addField("name_f", ___("First Name"));
        $grid->addField("name_l", ___("Last Name"));
        $grid->addField("email", ___("E-Mail Address"));
        $grid->run($this->getResponse());
    }


    /**
     * For Admin CP->Setup->Email 'test' function
     */
    function testAction()
    {
        check_demo();
        
        $config = $this->getDi()->config;

        foreach ($this->getRequest()->toArray() as $k => $v)
            $config->set($k, strip_tags($v));

        $m = new Am_Mail;
        $m->addTo($this->getParam('email'), 'Test E-Mail')
          ->setSubject('Test E-Mail Message from aMember ')
          ->setBodyText("This is a test message sent from aMember CP\n\nURL: " . htmlentities(ROOT_URL));
        
        try {
            $m->send(new Am_Mail_Queue($config));
        } catch (Exception $e) {
            echo "<span class='error'>Error during e-mail sending: " . get_class($e). ":" . $e->getMessage()  ."</span>";
            return;
        };

        $f = current(Am_Mail::getDefaultFrom());
        $e = htmlentities($this->getParam('email'));
        $tm = date('Y-m-d H:i:s');
        print "<p>Message has been sent successfully. Please wait 2 minutes and check the mailbox <i>$e</i>.<br />".
            "There must be a message with subject [Test E-Mail]. Do not forget to check <i>Spam</i> folder.</p><br />" .
            "<p>If the message does not arrive shortly, contact your webhosting support and ask them to find <br />".
            "in <b>mail.log</b> what happened with a message sent from <i>$f</i> to <i>$e</i> at $tm</p>";
    }

    function getAttachments()
    {
        if (!$this->_request->getParam('files'))
            return array();
        if (!$this->_attachments)
        {
            $this->_attachments = array();
            foreach ($this->getDi()->uploadTable->findByIds($this->getParam('files'), 'email') as $f)
            {
                /* @var $f Upload */
                $at = new Zend_Mime_Part(file_get_contents($f->getFullPath(), 'r'));
                $at->type = $f->getType();
                $at->filename = $f->getName();
                $at->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
                $at->encoding = Zend_Mime::ENCODING_BASE64;
                $this->_attachments[] = $at;
            }
        }
        return $this->_attachments;
    }

    function createSendSession()
    {
        $saved = $this->getDi()->emailSentTable->createRecord();
        $saved->admin_id = $this->getDi()->authAdmin->getUserId();
        $vars = $this->getRequest()->toArray();
        
        $saved->serialize($this->getRequest()->toArray());
        $saved->count_users = $this->searchUi->getFoundRows();
        $saved->desc_users = $this->searchUi->getActive()->getDescription();
        $saved->sent_users = 0;
        $saved->is_cancelled = 0;
        $saved->newsletter_ids = implode(',', array_filter(array_map('intval', $this->searchUi->getTargetListIds())));
        $saved->insert();
        $this->saved = $saved;
    }

    function sendRedirect()
    {
        $done = $this->saved->sent_users;
        $total = $this->saved->count_users;
        $url = $this->getUrl(null, 'send', null, array('queue_id' => $this->saved->pk()));
        $text = $total > 0 ? (___("Sending e-mail (sent to %d from %d)", $done, $total) . ". " . ___("Please wait")) : 
            ___("E-Mail sending started");
        $text .= "...";
        $this->redirectHtml($url, $text, ___("E-Mail Sending") . '...');
    }

    function sendComplete()
    {
        $this->saved->updateQuick('tm_finished', $this->getDi()->sqlDateTime);
        $total = $this->saved->count_users;
        $this->view->assign('title', ___('Email Sent'));
        ob_start();
        $queue_id = $this->getFiltered('queue_id');
        print "<div>";
        __e("E-Mail has been successfully sent to %s customers. E-Mail Batch ID is %s", $total, $queue_id);
        print "</div>\n";
        $url = $this->getUrl(null, 'index');
        print "<div><a href='$url'>".___('Send New E-Mail')."</a></div>";
        print "<br><br><br>";
        $this->view->assign('content', ob_get_clean());
        $this->view->display('admin/layout.phtml');
    }

    function createForm()
    {
        $form = new Am_Form_Admin('am-form-email');
        $form->setDataSources(array($this->_request));
        $form->setAction($this->getUrl(null, 'preview'));
        $subj = $form->addElement('text', 'subject', array('size'=>80))
                     ->setLabel(array(___('Email Subject')));
        $subj->persistentFreeze(true); // ??? why is it necessary? but it is 
        $subj->addRule('required', 'Subject is required');
//        $arch = $form->addElement('advcheckbox', 'do_archive')->setLabel(array('Archive Message', 'if you are sending it to newsletter subscribers'));
        $format = $form->addGroup(null)->setLabel(___('E-Mail Format'));
        $format->addRadio('format', array('value'=>'html'))->setContent(___('HTML Message'));
        $format->addRadio('format', array('value'=>'text'))->setContent(___('Plain-Text Message'));

        $group = $form->addGroup('', array('id'=>'body-group', 'class' => 'no-label'))
            ->setLabel('Message Text');
        $group->addElement('textarea', 'body', array('id'=>'body-0', 'rows'=>'15', 'cols'=>'80', 'style' => 'width: 90%;'));

        
        $this->tagsOptions = Am_Mail_TemplateTypes::getInstance()->getTagsOptions('send_signup_mail');
        $tagsOptions = array();
        foreach ($this->tagsOptions as $k => $v)
            $tagsOptions[$k] = "$k - $v";
        $sel = $form->addSelect('', array('id'=>'insert-tags', ));
        $sel->loadOptions(array_merge(array(''=>''), $tagsOptions));

        
        
        $fileChooser = new Am_Form_Element_Upload('files',array('multiple'=>'1'), array('prefix'=>'email'));

        $form->addElement($fileChooser)->setLabel(___('Attach Uploaded File'));

        foreach ($this->searchUi->getHidden() as $k => $v)
            $form->addHidden($k)->setValue($v);

        $form->addElement('submit', 'send', array('value' => ___('Preview'), 'style'=>'width: 200px'));
        $id='body-0';
        $vars = "";
        foreach ($this->tagsOptions as $k => $v)
            $vars .= "['$k'],\n";
        $vars = trim($vars, "\n\r,");
        
        $form->addScript('_bodyscript')->setScript(<<<CUT
$(function(){
    // modified version of http://alexking.org/blog/2003/06/02/inserting-at-the-cursor-using-javascript
    $.fn.insertAtCaret = function (myValue) {
            return this.each(function(){
                    //IE support
                    if (document.selection) {
                            this.focus();
                            sel = document.selection.createRange();
                            sel.text = myValue;
                            this.focus();
                    }
                    //MOZILLA/NETSCAPE support
                    else if (this.selectionStart || this.selectionStart == '0') {
                            var startPos = this.selectionStart;
                            var endPos = this.selectionEnd;
                            var scrollTop = this.scrollTop;
                            this.value = this.value.substring(0, startPos)
                                          + myValue
                                  + this.value.substring(endPos,
    this.value.length);
                            this.focus();
                            this.selectionStart = startPos + myValue.length;
                            this.selectionEnd = startPos + myValue.length;
                            this.scrollTop = scrollTop;
                    } else {
                            this.value += myValue;
                            this.focus();
                    }
            });

    };
            
    $('select#insert-tags').change(function(){
        var val = $(this).val();
        if (!val) return;
        $("#$id").insertAtCaret(val);
        $(this).prop("selectedIndex", -1);
    });
            
    if (CKEDITOR.instances["$id"]) {
        delete CKEDITOR.instances["$id"];
    }
    var editor = null;
    $("input[name='format']").change(function()
    {
        if (!this.checked) return;
        if (this.value == 'html')
        {
            if (!editor) {
                editor = initCkeditor("$id", { placeholder_items: [
                    $vars
                ]});
            }
            $('select#insert-tags').hide();
        } else {
            if (editor) {
                editor.destroy();
                editor = null;
            }
            $('select#insert-tags').show();
        }
    }).change();
});            
   
CUT
);
        return $form;
    }

    /** @return Am_Form_Admin */
    function getForm()
    {
        if (!$this->form)
            $this->form = $this->createForm();
        return $this->form;
    }

    function previewAction()
    {
        $form = $this->getForm();
        if ($this->form->validate())
        {
            $form->toggleFrozen(true);

            $form->setAction($this->getUrl(null, 'send'));
            $form->getElementById('send-0')->setAttribute('value', ___('Send E-Mail Message'));
            $form->addElement('inputbutton', 'back', array(
                'value' => 'Back', 
                'style'=>'width: 200px',
                'class' => 'form-back', 
                'data-href' => $this->getUrl(null, 'index')));

            // remove text and add hidden instead
            $group = $form->getElementById('body-group');
            $group->removeChild($form->getElementById('body-0'));
            $group->addHidden('body');
            // now add it for display

            $form->addScript('_bodyscript')->setScript(<<<CUT
$(function(){
    var html = $("input[name='body']").val();
    // if format == 'text' add <br> after newlines
    if ($("input[name='format']").val() == 'text')
        html = html.replace(/\\n/g, "<br />\\n");
    $("#row-body-group .element.group").html(html);
    
    $("input.form-back").click(function(){
        $(this).closest('form').prop('action', $(this).data('href')).submit();
    });
});
CUT
            );

        }
        return $this->indexAction();
    }


    function indexAction()
    {
        $this->view->headScript()->appendFile(REL_ROOT_URL . "/application/default/views/public/js/ckeditor/ckeditor.js");
        $this->view->headScript()->appendFile(REL_ROOT_URL . "/application/default/views/public/js/htmlreg.js");
        $form = $this->getForm();

        if ($this->form->isSubmitted())
            $this->form->validate ();

        $this->view->form = $form;
        $this->view->users_found = $this->searchUi->getFoundRows();
        if ($this->_request->getActionName() != 'preview')
            $this->view->search = $this->searchUi->render();
        else
            $this->view->search = "<br /><br /><br />";
        $this->view->filterCondition = $this->searchUi->getActive()->getDescription();

        $this->view->display('admin/email.phtml');
    }
    
    function historyRowsAction()
    {
        $q = new Am_Query($this->getDi()->emailSentTable);
        $q->leftJoin('?_admin', 'a', 't.admin_id=a.admin_id');
        $q->addField('a.login', 'admin_login');
        $q->setOrder('email_sent_id', 'DESC');
        // dirty hack
        $withWrap = (bool)$this->_request->get('_h_wrap');
        unset($_GET['_h_wrap']);
        $grid = new Am_Grid_Editable('_h', ___("E-Mails History"), $q, $this->_request, $this->view);
        $grid->setPermissionId(Am_Auth_Admin::PERM_EMAIL);
        if ($withWrap) $grid->isAjax(false);
        $grid->setCountPerPage(5);
        $grid->addField('tm_added', ___('Started'));
        $grid->addField('subject', ___('Subject'));
        $grid->addField('admin_login', ___('Sender'));
        $grid->addField('count_users', ___('Total'));
        $grid->addField('sent_users', ___('Sent'));
        $grid->addField('desc_users', ___('To'));
        $grid->actionsClear();
        $grid->actionAdd(new Am_Grid_Action_Url('resend', ___("Resend"), REL_ROOT_URL . "/admin-email?resend_id=__ID__"))->setTarget('_top');
        $grid->actionAdd(new Am_Grid_Action_Url('continue', ___("Continue"), REL_ROOT_URL . "/admin-email?queue_id=__ID__"))
            ->setTarget('_top')
            ->setIsAvailableCallback(array($this, 'needContinueLink'));
        $grid->run($this->getResponse());
    }
    function needContinueLink(EmailSent $s)
    {
        return $s->count_users < $s->sent_users;
    }
    
    function sendAction()
    {
        if ($this->getParam('back'))
            return $this->_redirect('admin-email');

        check_demo();
        
        if (!$this->saved)
        {
            $this->createSendSession();
            return $this->sendRedirect();
        }
        
        $batch = new Am_BatchProcessor(array($this, 'batchSend'), 10);
        $breaked = !$batch->run($this->saved);
        $breaked ? $this->sendRedirect() : $this->sendComplete();
   }
    
    public function batchSend(&$context, Am_BatchProcessor $batch)
    {
        if ($this->saved->count_users <= $this->saved->sent_users)
            return true; // we are done;
        $q = $this->searchUi->query($this->saved->sent_users, 10);
        
        $t = new Am_View;
        $i = 0;
        $db = $this->getDi()->db;
        while ($r = $db->fetchRow($q))
        {
            $r['name'] = $r['name_f'] . ' ' . $r['name_l'];
            $this->saved->updateQuick(array('last_email'=>$r['email'], 'sent_users' => $this->saved->sent_users + 1 ));
            if ($r['email'] == '')
                continue;
            $subject = $this->getParam('subject');
            $body = $this->getParam('body');
            // assign variables
            $tpl = new Am_SimpleTemplate();
            $tpl->assignStdVars();
            $tpl->user = $r;
            $this->getDi()->hook->call(Am_Event::MAIL_SIMPLE_TEMPLATE_BEFORE_PARSE, array('template' =>$tpl,'body' => $body, 'subject' => $subject));
            $subject = $tpl->render($subject);
            $body = $tpl->render($body);
            // 
            $m = new Am_Mail;
            $m->addUnsubscribeLink(Am_Mail::LINK_USER);
            $m->addTo($r['email'], $r['name'])
                ->setSubject($subject);
                //->setFrom($this->getDi()->config->get('admin_email'), $this->getDi()->config->get('site_title') . ' Admin');
            if ($this->getParam('format') == 'text')
                $m->setBodyText($body);
            else
                $m->setBodyHtml($body);
            $m->setPeriodic(Am_Mail::ADMIN_REQUESTED);
            $m->addHeader('X-Amember-Queue-Id', $this->_request->getFiltered('queue_id'));
            foreach ($this->getAttachments() as $at)
                $m->addAttachment($at);
            try
            {
                $m->send();
            }
            catch (Zend_Mail_Exception $e)
            {
                trigger_error("Error happened while sending e-mail to $r[email] : " . $e->getMessage(), E_USER_WARNING);
            }
        }
        $this->getDi()->db->freeResult($q);
        if ($this->saved->count_users <= $this->saved->sent_users)
            return true; // we are done;
    }

    function preDispatch()
    {
        ignore_user_abort(true);
        @set_time_limit(0);
        @ini_set('memory_limit', '128M');
        if ($this->queue_id = $this->getFiltered('queue_id'))
        {
            $this->saved = $this->getDi()->emailSentTable->load($this->queue_id);
            $this->_request->fromArray($this->saved->unserialize());
        } elseif ($id = $this->getInt('resend_id')) {
            $this->saved = $this->getDi()->emailSentTable->load($id);
            unset($_GET['resend_id']);
            $this->getRequest()->fromArray($_POST = $this->saved->unserialize());
        }
        $this->_request->set('format', $this->getParam('format', 'html'));
        $this->searchUi = new Am_Query_Ui;
        $this->searchUi->addDefaults();
        $this->searchUi->setFromRequest($this->_request);
    }

}
