<?php

class Bootstrap_Helpdesk extends Am_Module
{
    const ATTACHMENT_UPLOAD_PREFIX = 'helpdesk-attachment';
    const ADMIN_ATTACHMENT_UPLOAD_PREFIX = 'helpdesk-admin-attachment';

    const EVENT_TICKET_AFTER_INSERT = 'helpdeskTicketAfterInsert';

    function init()
    {
        $this->getDi()->uploadTable->defineUsage(self::ATTACHMENT_UPLOAD_PREFIX, 'helpdesk_message', 'attachments', UploadTable::STORE_IMPLODE, "Ticket [%ticket_id%]", '/helpdesk/admin/p/index/index');
        $this->getDi()->uploadTable->defineUsage(self::ADMIN_ATTACHMENT_UPLOAD_PREFIX, 'helpdesk_message', 'attachments', UploadTable::STORE_IMPLODE, "Ticket [%ticket_id%]", '/helpdesk/admin/p/index/index');

        $this->getDi()->blocks->add(new Am_Block('member/main/top', null, 'helpdesk-notification', null, array($this, 'renderNotification')));
    }

    function renderNotification()
    {
        if ($user_id = $this->getDi()->auth->getUserId()) {
            $cnt = $this->getDi()->db->selectCell("SELECT COUNT(ticket_id) FROM ?_helpdesk_ticket WHERE status IN (?a) AND user_id=?",
                array(HelpdeskTicket::STATUS_AWAITING_USER_RESPONSE), $user_id);

            if ($cnt) return '<div class="am-info">' . ___('You have %s%d tickets%s that require your attention',
                sprintf('<a href="%s">', ROOT_URL . '/helpdesk/index/p/index/index?&_member_filter_s[]=awaiting_user_response'), $cnt, '</a>') .
               '</div>';
        }
    }

    function onSetupEmailTemplateTypes(Am_Event $event)
    {
        $ticket = array (
            'ticket.ticket_mask' => 'Ticket Mask',
            'ticket.subject' => 'Ticket Subject',
        );

        $event->addReturn(array(
                'id' => 'helpdesk.notify_new_message',
                'title' => 'Notify New Message',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => $ticket + array('url' => 'Url of Page with Message', 'user'),
            ), 'helpdesk.notify_new_message');
        $event->addReturn(array(
                'id' => 'helpdesk.notify_new_message',
                'title' => 'Notify New Message',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => $ticket + array('url' => 'Url of Page with Message', 'user'),
            ), 'helpdesk.notify_new_message_admin');
        $event->addReturn(array(
                'id' => 'helpdesk.new_ticket',
                'title' => 'Autoresponder New Ticket',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => $ticket +  array('url' => 'Url of Page with Ticket', 'user'),
            ), 'helpdesk.new_ticket');
    }

     function onGetUploadPrefixList(Am_Event $event)
    {
        $event->addReturn(array(
            Am_Upload_Acl::IDENTITY_TYPE_ADMIN => array(
                'helpdesk' => Am_Upload_Acl::ACCESS_ALL
            ),
            Am_Upload_Acl::IDENTITY_TYPE_USER => Am_Upload_Acl::ACCESS_WRITE | Am_Upload_Acl::ACCESS_READ_OWN
        ), self::ATTACHMENT_UPLOAD_PREFIX);
    }

    function onLoadAdminDashboardWidgets(Am_Event $event)
    {
        $event->addReturn(new Am_Widget('helpdesk-messages', ___('Last Messages in Helpdesk'), array($this, 'renderWidget'), Am_Widget::TARGET_ANY, array($this, 'createWidgetConfigForm'), 'helpdesk'));
    }

    function createWidgetConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addInteger('num')
            ->setLabel(___('Number of Messages to display'))
            ->setValue(5);

        return $form;
    }

    function renderWidget(Am_View $view, $config = null)
    {
       $view->num = is_null($config) ? 5 : $config['num'];
       return $view->render('admin/helpdesk/widget/messages.phtml');
    }

    function onClearItems(Am_Event $event)
    {
        $event->addReturn(array (
            'method' => array($this->getDi()->helpdeskTicketTable, 'clearOld'),
            'title'  => 'Helpdesk Tickets',
            'desc'   => 'records with last update date early than Date to Purge'
        ), 'helpdesk_tickets');
    }

    function  onAdminWarnings(Am_Event $event)
    {
        $cnt = $this->getDi()->db->selectCell("SELECT COUNT(ticket_id) FROM ?_helpdesk_ticket WHERE status IN (?a)",
            array(HelpdeskTicket::STATUS_AWAITING_ADMIN_RESPONSE, HelpdeskTicket::STATUS_NEW));

        if ($cnt) $event->addReturn(___('You have %s%d tickets%s that require your attention',
            sprintf('<a href="%s">', ROOT_URL . '/helpdesk/admin/?_admin_filter_s[]=new&_admin_filter_s[]=awaiting_admin_response'), $cnt, '</a>'));
    }

    function onUserMerge(Am_Event $event)
    {
        $target = $event->getTarget();
        $source = $event->getSource();

        $this->getDi()->db->query('UPDATE ?_helpdesk_ticket SET user_id=? WHERE user_id=?',
            $target->pk(), $source->pk());
    }
    function onAdminMenu(Am_Event $event)
    {
        $event->getMenu()->addPage(array(
            'label' => ___('Helpdesk'),
            'uri' => '#',
            'id' => 'helpdesk',
            'resource' => "helpdesk",
            'pages' => array(
                array(
                    'label' => ___('All Tickets'),
                    'controller' => 'admin',
                    'action' => 'index',
                    'module' => 'helpdesk',
                    'id' => 'helpdesk-tickets',
                    'resource' => "helpdesk",
                    'params' => array (
                        'page_id' => 'index'
                    ),
                    'route' => 'inside-pages'
                ),
                array(
                    'label' => ___('My Tickets'),
                    'controller' => 'admin-my',
                    'action' => 'index',
                    'module' => 'helpdesk',
                    'id' => 'helpdesk-tickets-my',
                    'resource' => "helpdesk",
                    'params' => array (
                        'page_id' => 'index'
                    ),
                    'route' => 'inside-pages'
                ),
                array(
                    'label' => ___('Categories'),
                    'controller' => 'admin-category',
                    'action' => 'index',
                    'module' => 'helpdesk',
                    'id' => 'helpdesk-category',
                    'resource' => "helpdesk"
                ),
                array(
                    'label' => ___('FAQ'),
                    'controller' => 'admin-faq',
                    'action' => 'index',
                    'module' => 'helpdesk',
                    'id' => 'helpdesk-faq',
                    'resource' => "helpdesk"
                ))
        ));
    }
    function onUserMenu(Am_Event $event)
    {
        $event->getMenu()->addPage(
            array(
                'id' => 'helpdesk',
                'label' => ___('Helpdesk'),
                'controller' => 'index',
                'action' => 'index',
                'params' => array('page_id' => 'index'),
                'module' => 'helpdesk',
                'order' => 600,
                'route' => 'inside-pages',
            )
        );
        if (!$this->getConfig('does_not_show_faq_tab') && $this->getDi()->helpdeskFaqTable->countBy()) {
            $event->getMenu()->addPage(
                array(
                    'id' => 'helpdesk-faq',
                    'label' => ___('FAQ'),
                    'controller' => 'faq',
                    'action' => 'index',
                    'module' => 'helpdesk',
                    'order' => 601,
                ));
        }
    }
    function onUserTabs(Am_Event_UserTabs $event)
    {
        $event->getTabs()->addPage(array(
            'id' => 'helpdesk',
            'module' => 'helpdesk',
            'controller' => 'admin-user',
            'action' => 'index',
            'params' => array (
                            'user_id' => $event->getUserId()
                        ),
            'label' => ___('Tickets'),
            'order' => 1000,
            'resource' => 'helpdesk',
        ));
    }
    function onGetPermissionsList(Am_Event $event)
    {
        $event->addReturn(___("Can open and answer helpdesk tickets"), "helpdesk");
    }    
    
    function onUserAfterDelete(Am_Event_UserAfterDelete $event) 
    {
        $this->getDi()->db->query("DELETE FROM ?_helpdesk_message WHERE 
            ticket_id IN (SELECT ticket_id FROM ?_helpdesk_ticket
            WHERE user_id=?)", $event->getUser()->user_id);
        $this->getDi()->db->query("DELETE FROM ?_helpdesk_ticket
            WHERE user_id=?", $event->getUser()->user_id);
    }
    
    function onInitFinished() {
       $this->getDi()->register('helpdeskStrategy', 'Am_Helpdesk_Strategy_Abstract')
            ->setConstructor('create');


        $router = Zend_Controller_Front::getInstance()->getRouter();
        $router->addRoute('helpdesk-item', new Zend_Controller_Router_Route(
              'helpdesk/faq/i/:title', array(
                  'module' => 'helpdesk',
                  'controller' => 'faq',
                  'action' => 'item'
              )
        ));
        $router->addRoute('helpdesk-category', new Zend_Controller_Router_Route(
              'helpdesk/faq/c/:cat', array(
                  'module' => 'helpdesk',
                  'controller' => 'faq',
                  'action' => 'index'
              )
        ));
       
    }
    
    function onBuildDemo(Am_Event $event)
    {
        $subjects = array(
            'Please help',
            'Urgent question',
            'I have a problem',
            'Important question',
            'Pre-sale inquiry',
        );
        $questions = array(
            "My website is now working. Can you help?",
            "I have a problem with website script.\nWhere can I find documentation?",
            "I am unable to place an order, my credit card is not accepted.",
        );
        $answers = array(
            "Please call us to phone# 1-800-222-3334",
            "We are looking to your problem, and it will be resolved within 4 hours",
        );
        $user = $event->getUser();
        /* @var $user User */
        while (rand(0,10)<4)
        {
            $now = $this->getDi()->time;

            $created = sqlTime($now - rand(3600, 3600*24*180));

            $ticket = $this->getDi()->helpdeskTicketRecord;
            $ticket->status = HelpdeskTicket::STATUS_AWAITING_ADMIN_RESPONSE;
            $ticket->subject = $subjects[ rand(0, count($subjects)-1) ];
            $ticket->user_id = $user->pk();
            $ticket->created = sqlTime($created);
            $ticket->updated = sqlTime($created);
            $ticket->insert();
            //
            $msg = $this->getDi()->helpdeskMessageRecord;
            $msg->content = $questions[ rand(0, count($questions)-1) ];
            $msg->type = 'message';
            $msg->ticket_id = $ticket->pk();
            $msg->dattm = $tm = sqlTime($created);
            $msg->insert();
            //
            if (rand(0, 10)<6)
            {
                $msg = $this->getDi()->helpdeskMessageRecord;
                $msg->content = $answers[ rand(0, count($answers)-1) ];
                $msg->type = 'message';
                $msg->ticket_id = $ticket->pk();
                $msg->dattm = sqlTime(min(strtotime($tm) + rand(180, 3600*24), $now));
                $msg->admin_id = $this->getDi()->adminTable->findFirstBy()->pk();
                $msg->insert();
                if (rand(0, 10)<6)
                    $ticket->status = HelpdeskTicket::STATUS_AWAITING_USER_RESPONSE;
                else
                    $ticket->status = HelpdeskTicket::STATUS_CLOSED;
                $ticket->updated = $msg->dattm;
                $ticket->update();
            }
        }
    }

    function onLoadReports() {
        require_once 'Am/Report/Helpdesk.php';
    }
}