<?php
class AdminCountriesController extends Am_Controller_Grid
{
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
        $form->addText("title")->setLabel(___("Title"))->addRule('required');
        return $form;
    }
    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->countryTable);
        $ds->addField('ABS(tag)', 'tag_abs');
        $ds->setOrderRaw('tag_abs desc, title');
        $grid = new Am_Grid_Editable('_c', ___("Browse Countries"), $ds, $this->_request, $this->view);
        $grid->addField(new Am_Grid_Field('tag_abs', ___('Sort Order'), true, null, null, '10%'));
        $grid->addField(new Am_Grid_Field('title', ___('Title'), true));
        $grid->addField(new Am_Grid_Field('country', ___('Code'), true));
        $grid->setForm(array($this, 'createForm'));
        $grid->actionAdd(new Am_Grid_Action_Url_Country('states', ___('Edit States'), '__ROOT__/admin-states/?country=__COUNTRY__'))->setTarget('_top');
        $grid->actionDelete('delete');
        $grid->actionDelete('insert');
        $grid->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, array($this,'getTrAttribs'));
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, array($this, 'valuesToForm'));
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, array($this, 'valuesFromForm'));
        $grid->actionAdd(new Am_Grid_Action_LiveEdit('title'));
        $grid->setFilter(new Am_Grid_Filter_Text(___('Filter by Counrty Title'), array('title' => 'LIKE')));
        return $grid;        
    }
    function valuesToForm(& $values, Country $record)
    {
        if($record->tag<0)
        {
            $values['_is_disabled'] = 1;
            $values['tag']*=-1;
        }
        else
            $values['_is_disabled'] = 0;
    }
    function valuesFromForm(& $values, Country $record)
    {
        if($values['_is_disabled'])
        {
            $values['tag'] = ($values['tag'] ? $values['tag']*-1 : -1);
        }
    }
    public function getTrAttribs(& $ret, $record)
    {
        if ($record->tag<0)
        {
            $ret['class'] = isset($ret['class']) ? $ret['class'] . ' disabled' : 'disabled';
        }
    }
}
class Am_Grid_Action_Url_Country extends Am_Grid_Action_Url
{
    public function getUrl($record = null, $id = null)
    {
        return str_replace(array('__ROOT__','__COUNTRY__'), array(REL_ROOT_URL, $record->country), $this->url);
    }    
}
