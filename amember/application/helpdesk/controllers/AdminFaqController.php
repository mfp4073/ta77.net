<?php

class Am_Form_Admin_HelpdeskFaq extends Am_Form_Admin {
    function init()
    {

        $cats = Am_Di::getInstance()->helpdeskFaqTable->getCategories();


        if($cats){
            $catoptions = array_filter($cats);
            array_unshift($catoptions, ___('-- Please Select --'));
        } else {
            $catoptions = array(''=>'');
        }

        $this->addSelect('category', array(),
            array('intrinsic_validation' => false, 'options' => $catoptions))
                ->setLabel('Category');

        $this->addScript()
            ->setScript(<<<CUT
jQuery(function($){
    $("select[name='category']").prop("id", "category").after($("<span><a href='javascript:' id='add-category'>add category</a></span>"));

    $("select[name='category']").change(function(){
        $(this).toggle($(this).find('option').length > 1);
    }).change();


    $("a#add-category").live('click', function(){
        var ret = prompt("Enter title for your new category", "");
        if (!ret) return;
        var \$sel = $("select#category").append(
            $("<option></option>").val(ret).html(ret));
        \$sel.val(ret).change();
    });
})
CUT
            );

        $this->addText('title', array('size'=>40))
            ->setLabel(___('Title'));
        $this->addHtmlEditor('content')
            ->setLabel(___('Content'));
    }
}

class Am_Grid_Filter_HelpdeskFaq extends Am_Grid_Filter_Abstract
{
    public function getTitle()
    {
        return ___("Filter By Title or Category");
    }

    protected function applyFilter()
    {
        if ($this->isFiltered())
        {
            $q = $this->grid->getDataSource();
            /* @var $q Am_Query */
            $q->addWhere('title LIKE ? OR category LIKE ?',
                '%' . $this->vars['filter'] . '%',
                '%' . $this->vars['filter'] . '%');
        }
    }

    public function renderInputs()
    {
        return $this->renderInputText();
    }
}

class Helpdesk_AdminFaqController extends Am_Controller_Grid {

    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('helpdesk');
    }

    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->helpdeskFaqTable);
        $grid = new Am_Grid_Editable('_helpdesk_faq', ___("FAQ"), $ds, $this->_request, $this->view);

        $grid->addGridField(new Am_Grid_Field('title', ___('Title'), true, '', null, '50%'));
        $grid->addGridField(new Am_Grid_Field('category', ___('Category')));
        $grid->setForm('Am_Form_Admin_HelpdeskFaq');
        $grid->setFilter(new Am_Grid_Filter_HelpdeskFaq());

        return $grid;
    }
}
