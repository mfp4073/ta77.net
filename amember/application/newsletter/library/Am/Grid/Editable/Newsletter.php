<?php

class Am_Grid_Editable_Newsletter extends Am_Grid_Editable_Content
{
    public function __construct(Am_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $a = new Am_Grid_Action_Callback('_refresh', ___('Refresh 3-rd party lists'), array($this, 'doRefreshLists'), Am_Grid_Action_Abstract::NORECORD);
        $this->actionAdd($a);
        $this->actionAdd(new Am_Grid_Action_NewsletterSubscribeAll());
        $this->refreshLists(false); // refresh if expired
        foreach ($this->getActions() as $action) {
            $action->setTarget('_top');
        }
        $this->setFilter(new Am_Grid_Filter_Text(___('Filter by Title'), array('title'=>'LIKE')));
    }
    
    public function doRefreshLists(Am_Grid_Action_Callback $action)
    {
        $this->refreshLists(true);
        echo ___("Done");
        echo "<br /><br />";
        echo $action->renderBackButton(___("Continue"));
    }
    
    public function refreshLists($force=true)
    {
        $this->getDi()->newsletterListTable->disableDisabledPlugins(
            $this->getDi()->plugins_newsletter->getEnabled());
        foreach ($this->getDi()->plugins_newsletter->loadEnabled()->getAllEnabled() as $pl)
        {
            if (!$pl->canGetLists()) continue;
            $k = 'newsletter_plugins_' . $pl->getId() . '_lists';
            if (!$force && $this->getDi()->store->get($k))
                continue; // it is stored
            $lists = $pl->getLists();
            $this->getDi()->newsletterListTable->syncLists($pl, $lists);
            $this->getDi()->store->set($k, serialize($lists), '+1 hour');
        }
    }
    
    public function getLists()
    {
        $ret = array();
        foreach ($this->getDi()->plugins_newsletter->loadEnabled()->getAllEnabled() as $pl)
        {
            if (!$pl->canGetLists()) continue;
            $k = 'newsletter_plugins_' . $pl->getId() . '_lists';
            $s = $this->getDi()->store->get($k);
            if ($s) 
                $ret[$pl->getId()] = (array)unserialize($s);
        }
        return $ret;
    }
    
    protected function initGridFields()
    {
        $this->addGridField('title', ___('Title'));
        if (count($this->getDi()->plugins_newsletter->getEnabled()) > 1)
        {
            $this->addGridField('plugin_id', ___('Plugin'));
            $this->addGridField('plugin_list_id', ___('Plugin List Id'));
        }
        $this->addGridField('subscribed_users', ___('Subscribers'))
            ->addDecorator(new Am_Grid_Field_Decorator_Link('admin-users/index?_u_search[-newsletters][val][]={list_id}'));
        parent::initGridFields();
    }

    protected function createAdapter()
    {
        $q = new Am_Query(Am_Di::getInstance()->newsletterListTable);
        $q->addWhere('IFNULL(disabled,0)=0');
        $q->leftJoin('?_newsletter_user_subscription', 's', 's.list_id = t.list_id AND s.is_active > 0');
        $q->addField('COUNT(s.list_id)', 'subscribed_users');
        return $q;
    }
    
    function createForm()
    {
        $form = new Am_Form_Admin;
        $record = $this->getRecord();
        if ($record->isLoaded())
        {
            if ($record->plugin_id)
            {
                $s = $record->plugin_id . ' ' . Am_Controller::escape($record->plugin_list_id);
                $form->addStatic()->setLabel('Plugin/List ID')->setContent($s);
            }
        } else {
            $plugins = $this->getDi()->plugins_newsletter->loadEnabled()->getAllEnabled();
            if (count($plugins)>1)
            {
                $sel = $form->addSelect('plugin_id')->setLabel(___('Plugin'));
                $sel->addOption(___('Standard'),'');
                foreach ($plugins as $pl)
                {
                    if (!$pl->canGetLists() && $pl->getId() != 'standard')
                        $sel->addOption($pl->getTitle(), $pl->getId());
                }
            }
            $form->addText('plugin_list_id')->setLabel(___("Plugin List Id")."\n".___("value required"));
            $form->addScript()->setScript(<<<END
$(function(){
    $("#plugin_id-0").change(function(){
        var txt = $("input#plugin_list_id-0");
        var enabled = $(this).val() != '';
        txt.closest(".row").toggle(enabled);
        if (enabled)
            txt.rules("add", { required : true});
        else
            txt.rules("remove", "required");
    });
    $("input#plugin_list_id-0").closest(".row").hide();
});
END
);
        }
        
        
        $form->addText('title', array('size'=>80))->setLabel(___('Title'))->addRule('required');
        $form->addText('desc', array('size'=>80))->setLabel(___('Description'));
        
        $form->addAdvCheckbox('auto_subscribe')->setLabel(___("Auto-Subscribe users to list\n".
            "once it becomes accessible for them"));
        
        $form->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')
            ->setLabel(___('Access Permissions'))
            ->setAttribute('without_free_without_login', 'true')
            ->setAttribute('without_period', 'true');
            
        return $form;
    }
    
}