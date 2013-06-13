<?php

class AdminBanController extends Am_Controller_Pages
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }
    public function initPages()
    {
        $this->addPage(array($this, 'createGrid'), 'ip', ___('IP Address'))
             ->addPage(array($this, 'createGrid'), 'email', ___('E-Mail Address'))
             ->addPage(array($this, 'createGrid'), 'login', ___('Username'));
    }
    
    function createGrid($id, $title)
    {
        $ds = new Am_Query($this->getDi()->banTable);
        $ds->addWhere("`type` = ?", $id);
        $g = new Am_Grid_Editable('_'.$id, ___("Disallow new Signups by %s", $title), $ds, $this->_request, $this->view);
        $g->setForm(array($this, 'createForm'));
        $g->addGridField("value", ___("Locked Value"));
        $g->addGridField("comment", ___("Comment"));
        $g->addCallback(Am_Grid_ReadOnly::CB_RENDER_TABLE, array($this, 'renderConfig'));
        return $g;
    }
    public function createForm(Am_Grid_Editable $grid)
    {
        $id = substr($grid->getId(),1);
        $form = new Am_Form_Admin;
        $form->addText("value", array('size' => 40))->setLabel(___("Value\nuse % as wildcard mask"));
        $form->addHidden("type")->setValue($id);
        $form->addText('comment', array('size' => 40))->setLabel(___("Comment"));
        return $form;
    }
    public function configSaveAction()
    {
        $type = $this->getRequest()->getFiltered('c');
        if ($type == 'default') $type = 'ip';
        $action = $this->getFiltered('a');
        Am_Config::saveValue('ban.'.$type.'_action', $action);
        //echo $this->getJson(array('result' => 'ok', 'ban.'.$type.'_action' =>  $action));
        $this->_redirect('admin-ban?c='.$type);
    }
    public function renderConfig(& $output, $grid)
    {
        $type = substr($grid->getId(), 1);
        $url = $this->escape($this->getUrl(null, 'config-save'));
        $checked1 = $checked2 = "";
        if ($this->getDi()->config->get('ban.'.$type.'_action') == 'die')
            $checked2 = 'selected="selected"';
        else
            $checked1 = 'selected="selected"';
        
        $text = ___("Choose action when locked %s used by customer during signup", '['.$type.']');
        $opt1 = ___("Display error message");
        $opt2 = ___("Die and show ugly error message");
        $output .= <<<CUT
        <br /><br />
        <form method="post" action="$url">
        $text
        <input type="hidden" name="c" value="$type">
        <select name="a" id="{$type}-action" onchange="this.form.submit()">
            <option value="error" $checked1>$opt1</option>
            <option value="die" $checked2>$opt2</option>
        </select>
        </form>
        </script>
CUT;
    }
}