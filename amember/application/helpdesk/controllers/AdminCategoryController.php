<?php

class Am_Form_Admin_HelpdeskCategory extends Am_Form_Admin {
    function init()
    {
        $this->addText('title', array('size'=>40))
            ->setLabel(___('Title'));

        $options = array(''=>'');
        foreach(Am_Di::getInstance()->adminTable->findBy() as $admin) {
            $options[$admin->pk()] = sprintf('%s (%s %s)', $admin->login, $admin->name_f, $admin->name_l);
        }

        $this->addSelect('owner_id')
            ->setLabel(___('Set the following admin as owner of ticket'))
            ->loadOptions($options);
    }
}

class Helpdesk_AdminCategoryController extends Am_Controller_Grid {

    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('helpdesk');
    }

    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->helpdeskCategoryTable);
        $ds->leftJoin('?_admin', 'a', 't.owner_id=a.admin_id')
            ->addField("CONCAT(a.login, ' (',a.name_f, ' ', a.name_l, ')')", 'owner');

        $grid = new Am_Grid_Editable('_helpdesk_category', ___("Ticket Categories"), $ds, $this->_request, $this->view);

        $grid->addGridField(new Am_Grid_Field('title', ___('Title'), true, '', null, '50%'));
        $grid->addGridField(new Am_Grid_Field('owner_id', ___('Owner'), true, '', array($this, 'renderOwner')));
        $grid->addGridField(new Am_Grid_Field_IsDisabled);
        $grid->setForm('Am_Form_Admin_HelpdeskCategory');
        $grid->addCallback(Am_Grid_Editable::CB_BEFORE_SAVE, array($this, 'beforeSave'));
        return $grid;
    }

    public function renderOwner($record)
    {
        return $record->owner_id ?
            sprintf('<td>%s</td>', Am_Controller::escape($record->owner)) :
            '<td></td>';
    }

    public function beforeSave(& $values, $record)
    {
        $values['owner_id'] = $values['owner_id'] ? $values['owner_id'] : null;
    }
}
