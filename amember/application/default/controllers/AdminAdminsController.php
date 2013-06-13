<?php 
/*
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Admin accounts
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision$)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*                                                                          
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class AdminAdminsController extends Am_Controller_Grid
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }
    
    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->adminTable);
        $grid = new Am_Grid_Editable('_admin', ___('Admin Accounts'), $ds, $this->_request, $this->view);
        $grid->addField(new Am_Grid_Field('admin_id', '#', true, '', null, '5%'));
        $grid->addField(new Am_Grid_Field('login', ___('Username'), true));
        $grid->addField(new Am_Grid_Field('email', ___('E-Mail'), true));
        $grid->addField(new Am_Grid_Field('super_user', ___('Super Admin'), true))
            ->setRenderFunction('$v->super_user?___("Yes"):___("No")');
        $grid->addGridField(new Am_Grid_Field('last_login', ___('Last login'), true))
            ->setRenderFunction(array($this, 'renderLoginAt'));
        $grid->setForm(array($this, 'createForm'));
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, array($this, 'valuesToForm'));
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, array($this, 'valuesFromForm'));
        $grid->addCallback(Am_Grid_Editable::CB_BEFORE_SAVE, array($this, 'beforeSave'));
        $grid->addCallback(Am_Grid_Editable::CB_BEFORE_DELETE, array($this, 'beforeDelete'));
        return $grid;
    }
    
    public function checkSelfPassword($pass) {
        return $this->getDi()->authAdmin->getUser()->checkPassword($pass);
    }

    public function createForm() {
        $mainForm = new Am_Form_Admin();
        $self_password = $mainForm->addPassword('self_password')
                ->setLabel(___("Your Password\n".
                    "enter your current password\n".
                    "in order to edit admin record"));
        $self_password->addRule('callback', ___('Wrong password'), array($this, 'checkSelfPassword'));

        $form = $mainForm->addFieldset()->setLabel(___('Admin Settings'));
        $login = $form->addText('login')
            ->setLabel(___('Admin Username'));
        
        $login->addRule('required')
            ->addRule('length', ___('Length of username must be from %d to %d', 4, 16), array(4,16))
            ->addRule('regex', ___('Admin username must be alphanumeric in small caps'), '/^[a-z][a-z0-9_-]+$/');

        $set = $form->addGroup()->setLabel(___('First and Last Name'));
        $set->addText('name_f');
        $set->addText('name_l');

        $pass = $form->addPassword('_passwd')
            ->setLabel(___('New Password'));
        $pass->addRule('length', ___('Length of admin password must be from %d to %d', 6, 16), array(6,16));
        $pass->addRule('neq', ___('Password must not be equal to username'), $login);
        $pass0 = $form->addPassword('_passwd0')
            ->setLabel(___('Confirm New Password'));
        $pass0->addRule('eq', ___('Passwords must be the same'), $pass);

        $form->addText('email')
            ->setLabel(___('E-Mail Address'))
            ->addRule('required');
        $super = $form->addAdvCheckbox('super_user')
            ->setLabel(___('Super Admin'));

        $record = $this->grid->getRecord();

        if ($this->getDi()->authAdmin->getUserId() == $record->get('admin_id'))
            $super->toggleFrozen(true);
        $group = $form->addGroup('perms')
            ->setLabel(___('Permissions'))->setSeparator('<br />');
        foreach ($this->getDi()->authAdmin->getPermissionsList() as $perm => $title)
        {
            if (is_string($title))
                $group->addCheckbox($perm)->setContent($title);
            else {
                $gr = $group->addGroup($perm);
                $gr->addStatic()->setContent($title['__label']);
                unset($title['__label']);
                foreach ($title as $k => $v)
                    $gr->addCheckbox($k)->setContent($v);
            }
        }
        return $mainForm;
    }
    function renderLoginAt(Admin $a){
        return $this->renderTd($a->last_login ? $a->last_ip . ' at ' . $a->last_login : null);
    }
    
    function valuesToForm(& $values, Admin $record)
    {
        $values['perms'] = $record->getPermissions();
    }
    function valuesFromForm(& $values, Admin $record)
    {
        $values['perms'] || ($values['perms'] = array());
        $record->setPermissions($values['perms']);
        unset($values['perms']);
    }
    
    public function beforeSave(array & $values, $record) {
        check_demo();
        unset($values['self_password']);
        if (!$values['super_user']) { $values['super_user'] = 0; }
        if (!empty($values['_passwd']))
            $record->setPass($values['_passwd']);
    }
    public function beforeDelete($record){
        if ($this->getDi()->authAdmin->getUserId() == $record->admin_id)
            throw new Am_Exception_InputError("You can not delete your own account");
    }
}