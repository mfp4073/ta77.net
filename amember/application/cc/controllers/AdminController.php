<?php

class Cc_AdminController extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('cc');
    }
    
    function infoTabAction()
    {
        require_once APPLICATION_PATH . '/default/controllers/AdminUsersController.php';

        $this->setActiveMenu('users-browse');

        $user_id = $this->_request->getInt('user_id');
        if (!$user_id) throw Am_Exception_InputError("Empty [user_id] passsed");
        
        $cc = $this->getDi()->ccRecordTable->findFirstByUserId($user_id);
        /* @var $cc CcRecord */
        $this->view->cc = $cc;
        $this->view->addUrl = $this->getUrl(null, null, null, 'user_id', $this->getInt('user_id'), array('add'=>1));
        if ($cc || $this->_request->getInt('add') || $this->_request->get('_save_'))
        {
            $form = $this->createAdminForm((bool)$cc);
            if ($form)
            {   
                if ($form->isSubmitted() && $form->validate())
                {
                    if (!$cc) $cc = $this->getDi()->ccRecordTable->createRecord();
                    $form->toCcRecord($cc);
                    $cc->user_id = $user_id;
                    $cc->save();
                } elseif ($cc) {
                    $arr = $cc->toArray();
                    unset($arr['cc_number']);
                    $form->addDataSource(new HTML_QuickForm2_DataSource_Array($arr));
                }
                $this->view->form = $form;
                $this->view->form->setAction($this->_request->getRequestUri());
            }
        }
        $this->view->display('admin/cc/info-tab.phtml');
    }
    function createAdminForm($isUpdate)
    {
        $form = null;
        foreach ($this->getDi()->modules->get('cc')->getPlugins() as $ps)
        {
            $form = new Am_Form_CreditCard($ps, $isUpdate ? Am_Form_CreditCard::ADMIN_UPDATE : Am_Form_CreditCard::ADMIN_INSERT );
            break; // first one
        }
        return $form;
    }
    function changePaysysAction()
    {
        $form = new Am_Form_Admin;
        $form->setDataSources(array($this->_request));
        $form->addStatic()->setContent(___(
            'If you are moving from one payment processor, you can use this page to switch existing subscription from one payment processor to another. It is possible only if full credit card info is stored on aMember side.'));
        $options = array();
        foreach ($this->getModule()->getPlugins() as $ps)
        {
            $options[$ps->getId()] = $ps->getTitle();
        }
        $from = $form->addSelect('from')->setLabel('Move Active Invoices From')->loadOptions($options)
            ->addRule('required');
        
        $to = $form->addSelect('to')->setLabel('Move To')->loadOptions($options)
            ->addRule('required');
        
        $to->addRule('neq', ___('Values must not be equal'), $from);
        $form->addSaveButton();
        
        if ($form->isSubmitted() && $form->validate())
        {
            $vars = $form->getValue();
            $updated = $this->getDi()->db->query("UPDATE ?_invoice SET paysys_id=? WHERE paysys_id=? AND status IN (?a)",
                $vars['to'], $vars['from'], array(Invoice::RECURRING_ACTIVE));
            $this->view->content = "$updated rows changed. New rebills for these invoices will be handled with [{$vars['to']}]";
        } else {
            $this->view->content = (string)$form;
        }
        $this->view->title = ___("Change Paysystem");
        $this->view->display('admin/layout.phtml');
    }
}
