<?php

class Am_Form_Admin_ChangePassForm extends Am_Form_Admin 
{
    function init() {
        $self_password = $this->addPassword('self_password')
            ->setLabel(___("Your Password\n".
                "enter your current password\n".
                "in order to edit admin record"));
        
        $self_password->addRule('required');
        $self_password->addRule('callback', ___('Wrong password'), array($this, 'checkCurrentPassword'));

        $pass = $this->addPassword('pass')
                ->setLabel(___('New Password'));
        $pass->addRule('length', ___('Length of admin password must be from %d to %d', 6, 16), array(6,16));
        $pass->addRule('neq', ___('Password must not be equal to username'), Am_Di::getInstance()->authAdmin->getUser()->login);
        $pass0 = $this->addPassword('_passwd0')
            ->setLabel(___('Confirm New Password'));
        $pass0->addRule('eq', ___('Passwords must be the same'), $pass);
        parent::init();
        $this->addSaveButton();
    }

    public function checkCurrentPassword($pass) {
        return Am_Di::getInstance()->authAdmin->getUser()->checkPassword($pass);
    }
}


class AdminChangePassController extends Am_Controller 
{
    public function checkAdminPermissions(Admin $admin)
    {
        return (bool)$admin;
    }
    
    function indexAction() {

        $this->view->title = ___('Change Password');

        $form = new Am_Form_Admin_ChangePassForm();

        $form->setDataSources(array(
                $this->getRequest()
        ));

        if ($form->isSubmitted() && $form->validate()) {
            $vars = $form->getValue();
            $admin = $this->getDi()->authAdmin->getUser();
            $admin->setPass($vars['pass']);
            $admin->save();
            return $this->redirectLocation(REL_ROOT_URL . "/admin");
        }

        $this->view->form = $form;
        $this->view->display('admin/form.phtml');
    }
}

