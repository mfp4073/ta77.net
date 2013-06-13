<?php
class AdminStatesController extends Am_Controller_Grid
{
    function preDispatch()
    {
        $this->country = preg_replace("[^A_Z]", '', $this->_request->country);
        if (!$this->country)
            throw new Am_Exception_InputError("country is empty in " . get_class($this));
        return parent::preDispatch();
    }
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }
    function createForm()
    {
        $form = new Am_Form_Admin;
        $form->addInteger("tag")->setLabel(___("Sort order"))->addRule('required');
        $form->addAdvRadio('_is_disabled')->setLabel(___('Is&nbsp;Disabled?'))
            ->loadOptions(array('0' => ___('No'), '1' => ___('Yes')));
        $form->addText('title')->setLabel(___('Title'))->addRule('required');
        if (!$this->grid->getRecord()->pk())
        {
            
            $gr = $form->addGroup();
            $gr->addStatic()->setContent('<span>'.$this->country.'-</span>');
            $gr->addText('state',array('size'=>5))->addRule('required');        
            $gr->setLabel(___('Code'));
        }
        $form->addHidden('country');
        return $form;
    }
    function valuesToForm(& $values, State $record)
    {
        if($record->pk() && $record->tag<0)
        {
            $values['_is_disabled'] = 1;
            $values['tag']*=-1;
        }
        else
            $values['_is_disabled'] = 0;

    }
    function valuesFromForm(& $values, State $record)
    {
        if($values['_is_disabled'])
        {
            $values['tag'] = ($values['tag'] ? $values['tag']*-1 : -1);
        }
        if(!$record->pk())
            $values['state'] = $this->country.'-'.$values['state'];
    }
    public function getTrAttribs(& $ret, $record)
    {
        if ($record->tag<0)
        {
            $ret['class'] = isset($ret['class']) ? $ret['class'] . ' disabled' : 'disabled';
        }
    }
    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->stateTable);
        $ds = $ds->addWhere('t.country=?', $this->country);
        $ds->addField('ABS(tag)', 'tag_abs');
        $ds->setOrderRaw('tag_abs desc, title');
        $grid = new Am_Grid_Editable('_s', ___("Browse States"), $ds, $this->_request, $this->view);
        $grid->addField(new Am_Grid_Field('tag_abs', ___('Sort Order'), true, null, null, '10%'));
        $grid->addField(new Am_Grid_Field('title', ___('Title'), true));
        $grid->addField(new Am_Grid_Field('state', ___('Code'), true));
        $grid->addField(new Am_Grid_Field('country', ___('Country'), true));
        $grid->setForm(array($this, 'createForm'));
        $grid->actionDelete('delete');
        $grid->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, array($this,'getTrAttribs'));
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, array($this, 'valuesToForm'));
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, array($this, 'valuesFromForm'));
        $grid->actionAdd(new Am_Grid_Action_LiveEdit('title'));
        $grid->setFilter(new Am_Grid_Filter_Text(___('Filter by State Title'), array('title' => 'LIKE')));        
        return $grid;
        
    }
}
