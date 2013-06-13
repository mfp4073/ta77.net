<?php

class Am_Helpdesk_Strategy_Admin_User extends Am_Helpdesk_Strategy_Admin{

    protected $user_id;

    protected function getControllerName()
    {
        return 'admin-user';
    }

    public function assembleUrl($params, $route = 'default'){
        $router = Zend_Controller_Front::getInstance()->getRouter();
        return $router->assemble(array(
            'module' => 'helpdesk',
            'controller' => $this->getControllerName(),
            'user_id' => $this->getUserId()
        )+$params, $route, true);
    }

    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function createNewTicketForm()
    {
        $form = parent::createNewTicketForm();   

        $member = Am_Di::getInstance()->userTable->load($this->getUserId());

        $text = HTML_QuickForm2_Factory::createElement('html', 'loginOrEmail');
        $text->setLabel(___('User'))
            ->setHtml(sprintf('<div>%s %s (%s)</div>',
                $member->name_f,
                $member->name_l,
                $member->login
        ));
        $text->toggleFrozen(true);
        $form->insertBefore($text, $form->getElementById('loginOrEmail'));

        $form->removeChild(
            $form->getElementById('loginOrEmail')
        );

        $loginOrEmail = HTML_QuickForm2_Factory::createElement('hidden', 'loginOrEmail');
        $loginOrEmail->setValue($member->login);

        $form->addElement($loginOrEmail);


        $user_id = HTML_QuickForm2_Factory::createElement('hidden', 'user_id');
        $user_id->setValue($member->pk());

        $form->addElement($user_id);


        return $form;
    }

}

