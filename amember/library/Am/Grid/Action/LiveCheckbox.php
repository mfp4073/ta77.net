<?php

/**
 * Provide live-edit functionality for a checkbox field
 */
class Am_Grid_Action_LiveCheckbox extends Am_Grid_Action_Abstract
{
    protected $privilege = 'edit';
    protected $type = self::HIDDEN;
    protected $fieldName;
    protected $callback;
    /** @var Am_Grid_Decorator_LiveEdit */
    protected $decorator;
    protected static $jsIsAlreadyAdded = false;
    
    public function __construct($fieldName)
    {
        $this->fieldName = $fieldName;
        parent::__construct('live-checkbox-' . $fieldName, ___("Live Edit %s", ___(ucfirst($fieldName)) ));
    }
    public function setCallback($callback)
    {
        $this->callback = $callback;
    }
    public function setGrid(Am_Grid_Editable $grid)
    {
        parent::setGrid($grid);
        $this->decorator = new Am_Grid_Field_Decorator_LiveCheckbox($this);
        $grid->getField($this->fieldName)->addDecorator($this->decorator);
        if (!self::$jsIsAlreadyAdded) {
            $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_STATIC, array($this, 'renderStatic'));
            self::$jsIsAlreadyAdded = true;
        }
    }
    
    function renderStatic(& $out) {
        $out .= <<<CUT
<script type="text/javascript">
$(".live-checkbox").live('click', function(event)
{
    var vars = $(this).data('params');
    var t = this;
    vars[$(this).attr("name")] = this.checked ? 1 : 0;
    $.post($(this).data('url'), vars, function(res){
       if (res.ok && res.callback)
          eval(res.callback).call(t, res.newValue);
    });

});       
</script>    
CUT;
    }
    
    /** @return Am_Grid_Field_Decorator_LiveCheckbox */
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
            $newValue = $v;
            $this->log('LiveEdit [' . $this->fieldName . ']');
        }

        $resp = array(
            'ok' => true,
            'message' => ___("Field Updated"),
            'newValue' => $newValue
        );
        if ($this->callback)
            $resp['callback'] = $this->callback;

        Am_Controller::ajaxResponse($resp);
    }
}