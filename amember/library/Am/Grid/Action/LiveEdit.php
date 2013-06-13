<?php

/**
 * Provide live-edit functionality for a field
 */
class Am_Grid_Action_LiveEdit extends Am_Grid_Action_Abstract
{
    protected $privilege = 'edit';
    protected $type = self::HIDDEN;
    protected $fieldName;
    protected $placeholder = null;
    /** @var Am_Grid_Decorator_LiveEdit */
    protected $decorator;
    
    public function __construct($fieldName, $placeholder=null)
    {
        $this->placeholder = is_null($placeholder) ? ___('Click to Edit') : $placeholder;
        $this->fieldName = $fieldName;
        parent::__construct('live-edit-' . $fieldName, ___("Live Edit %s", ___(ucfirst($fieldName)) ));
    }
    public function setGrid(Am_Grid_Editable $grid)
    {
        parent::setGrid($grid);
        $this->decorator = new Am_Grid_Field_Decorator_LiveEdit($this);
        $grid->getField($this->fieldName)->addDecorator($this->decorator);
        $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_STATIC, array($this, 'renderStatic'));
    }
    
    function renderStatic(& $out) {
        $out .= <<<CUT
<script type="text/javascript">
// simple function to extract params from url
$("td:has(span.live-edit)").live('click', function(event)
{
    // protection against double run (if 2 live edit grids on page)
    if (event.liveEditHandled) return;
    event.liveEditHandled = true;
    //

    if ($(this).data('mode') == 'edit') return;

    (function() {
    var txt = $(this);
    var edit = txt.closest("td").find("input.live-edit");
    if (!edit.length) {
        edit = $(txt.attr("livetemplate"));
        if (txt.text() != txt.attr('placeholder')) {
            edit.val(txt.text());
        }
        txt.data("prev-val", edit.val());
        edit.attr("name", txt.attr("id"));
        edit.css({'width' : '90%'})
        txt.after(edit);
        edit.focus();
    }
    txt.hide();
    txt.closest('td').data('mode', 'edit');
    txt.closest('td').find('.editable').hide();
    edit.show();
    // bind outerclick event
    $("body").bind("click.inplace-edit", function(event){
        if (event.target != edit[0])
        {
            $("body").unbind("click.inplace-edit");
            var vars = $.parseJSON(txt.attr("livedata"));
            if (!vars) vars = {};
            vars[edit.attr("name")] = edit.val();
            if (edit.val() != txt.data('prev-val'))
                $.post(txt.attr("liveurl"), vars);
            txt.text(edit.val() ? edit.val() : txt.attr("placeholder"));
            edit.remove();
            txt.show();
            txt.closest('td').data('mode', 'display');
            txt.closest('td').find('.editable').show();
        }
    });
    }).apply($(this).find("span.live-edit").get(0));
});       
</script>    
CUT;
    }
    
    function getPlaceholder() {
        return $this->placeholder;
    }
    
    /** @return Am_Grid_Field_Decorator_LiveEdit */
    function getDecorator()
    {
        return $this->decorator;
    }
    public function getIdForRecord($obj)
    {
        return $this->grid->getDataSource()->getIdForRecord($obj);
    }
    public function run()
    {
        $prefix = $this->fieldName . '-';
        $ds = $this->grid->getDataSource();
        foreach ($this->grid->getRequest()->getPost() as $k => $v)
        {
            if (strpos($k, $prefix)===false) continue;
            $id = filterId(substr($k, strlen($prefix)));
            $record = $ds->getRecord($id);
            if (!$record) throw new Am_Exception_InputError("Record [$id] not found");
            $ds->updateRecord($record, array($this->fieldName => $v));
            $this->log('LiveEdit [' . $this->fieldName . ']');
        }
        Am_Controller::ajaxResponse(array('ok'=>true, 'message'=>___("Field Updated")));
    }
}