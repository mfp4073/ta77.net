<?php

class Bootstrap_Newsletter extends Am_Module
{
    const NEWSLETTER_SIGNUP_DATA = 'newsletter_signup_data';

    function init()
    {
        $this->getDi()->plugins_newsletter = new Am_Plugins($this->getDi(),
            'newsletter', dirname(__FILE__) . '/plugins',
            'Am_Newsletter_Plugin_%s', '%s.%s'
        );
        $this->getDi()->plugins_newsletter->addEnabled('standard');
        class_exists('Am_Newsletter_Plugin_Standard', true);

        $this->getDi()->plugins->offsetSet('newsletter', $this->getDi()->plugins_newsletter);
    }

    function renderComfirmation(Am_View $view)
    {
        return '<div class="am-info" style="display:none" id="unsubscribe-confirm">' .
            ___('Status of your subscription has been changed.') .
            '</div>';
    }

    function onInitFinished(Am_Event $event)
    {
        $this->getDi()->blocks->add(new Am_block('unsubscribe/before', ___("Comfirmation"), 'unsubscribe-comfirm',
                    null, array($this, 'renderComfirmation')));

        $this->getDi()->blocks->add(
            new Am_Block(array('member/main/left', 'unsubscribe'), ___("Newsletter Subscriptions"),
                'member-main-newsletter', $this, 'member-main-newsletter.phtml', Am_Block::MIDDLE)
        );

        $this->getDi()->blocks->add(new Am_block('unsubscribe', ___("Unsubscribe from all e-mail messages"), 'member-main-unsubscribe',
                    null, 'member-main-unsubscribe.phtml'));
    }
    
    function onInitAccessTables(Am_Event $event)
    {
        $event->getRegistry()->registerAccessTable($this->getDi()->newsletterListTable);
    }

    function onLoadBricks()
    {
        require_once 'Am/Form/Brick/Newsletter.php';
    }
    function onUserSearchConditions(Am_Event $event)
    {
        $event->addReturn(new Am_Query_User_Condition_SubscribedToNewsletter);
    }
    function onSignupUserAdded(Am_Event $event)
    {
        $vars = $event->getVars();
        if (!empty($vars['_newsletter']))
            $event->getUser()->data()->set(self::NEWSLETTER_SIGNUP_DATA, $vars['_newsletter'])->update();
        
        // handle free access newsletters; 
        $this->getDi()->newsletterUserSubscriptionTable->checkSubscriptions($event->getUser());
        
    }
    function onSignupUserUpdated(Am_Event $event)
    {
        $this->onSignupUserAdded($event);
    }
    function onUserAfterUpdate(Am_Event_UserAfterUpdate $event)
    {
        foreach ($this->getDi()->plugins_newsletter->loadEnabled()->getAllEnabled() as $pl)
        {
            $pl->onUserAfterUpdate($event);
        }
        $newEmail = $event->getUser()->get('email');
        $oldEmail = $event->getOldUser()->get('email');
        if ($newEmail != $oldEmail)
            foreach ($this->getDi()->plugins_newsletter->getAllEnabled() as $pl)
                $pl->changeEmail($event->getUser(), $oldEmail, $newEmail);
    }
    function onSubscriptionChanged(Am_Event_SubscriptionChanged $event)
    {
        $this->getDi()->newsletterUserSubscriptionTable->checkSubscriptions($event->getUser());
    }
    function onUserUnsubscribedChanged(Am_Event $event)
    {
        $this->getDi()->newsletterUserSubscriptionTable->checkSubscriptions($event->getUser());
    }

    function onGridUserInitForm(Am_Event_Grid $event)
    {
        $form = $event->getGrid()->getForm()->getElementById('general');
        $el = $form->addMagicSelect('_newsletter')->setLabel(___('Newsletter Subscriptions'));
        $el->loadOptions($this->getDi()->newsletterListTable->getAdminOptions());
        $record = $event->getGrid()->getRecord();
        if ($record->isLoaded())
            $el->setValue($ids = $this->getDi()->newsletterUserSubscriptionTable->getSubscribedIds($record->pk()));
        $form->addHidden('_newsletter_hidden')->setValue(1);
    }
    function onGridUserValuesToForm(Am_Event_Grid $event)
    {
        $args = $event->getArgs();
        $record = $event->getGrid()->getRecord();
        if ($record->isLoaded())
            $args[0]['_newsletter'] = $this->getDi()->newsletterUserSubscriptionTable->getSubscribedIds($record->pk());
        else
            $args[0]['_newsletter'] = array();
    }
    function onGridUserAfterSave(Am_Event_Grid $event)
    {
        $vars = $event->getArg(0);
        if (!empty($vars['_newsletter_hidden'])) // if was submitted
        {
            $vals = @$vars['_newsletter'];
            $this->getDi()->newsletterUserSubscriptionTable->adminSetIds($event->getGrid()->getRecord()->pk(), (array)$vals);
        }
    }
    function onUserAfterDelete(Am_Event_UserAfterDelete $event)
    {
        $this->getDi()->newsletterUserSubscriptionTable->deleteByUserId($event->getUser()->pk());
    }
    function onRebuild(Am_Event_Rebuild $event)
    {
        $this->getDi()->db->query("DELETE FROM ?_newsletter_user_subscription 
            WHERE list_id not in (SELECT list_id from ?_newsletter_list)");
        $batch = new Am_BatchProcessor(array($this, 'batchProcess'), 5);
        $context = $event->getDoneString();
        $this->_batchStoreId = 'rebuild-' . $this->getId() . '-' . Zend_Session::getId();
        if ($batch->run($context))
        {
            $event->setDone();
        } else
        {
            $event->setDoneString($context);
        }
    }
    function batchProcess(& $context, Am_BatchProcessor $batch)
    {
        $db = $this->getDi()->db;
        $q = $db->queryResultOnly("SELECT * FROM ?_user WHERE user_id > ?d", (int)$context);
        $userTable = $this->getDi()->userTable;
        $newsletterUserSubscriptionTable = $this->getDi()->newsletterUserSubscriptionTable;
        while ($r = $db->fetchRow($q))
        {
            $u = $userTable->createRecord($r);
            $context = $r['user_id'];
            $newsletterUserSubscriptionTable->checkSubscriptions($u);
            if (!$batch->checkLimits()) return;
        }
        return true;
    }
    function onGetPermissionsList(Am_Event $event)
    {
        $event->addReturn(___("Manage Newsletters"), "newsletter");
    }
}