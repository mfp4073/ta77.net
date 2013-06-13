<?php
/*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.amember.com/
*    Release: 4.2.17
*    License: LGPL http://www.gnu.org/copyleft/lesser.html
*/

/**
 * Represents a grid column
 * @package Am_Grid
 */
class Am_Grid_Field  {
    const LEFT = null;
    const CENTER = 'center';
    const RIGHT = 'right';

    protected $field;
    protected $title;
    protected $isSortable;
    protected $align; // left=''=default, center, right
    protected $renderFunc;
    protected $renderFuncIsEval = false;
    protected $getFunc;
    protected $formatFunc;
    /** @var string */
    protected $width;
    /** @var array Am_Grid_Field_Decorator_Abstract */
    protected $decorators = array();

    /**
     * Create new Grid field
     * @param string $field
     * @param string $title
     * @param string $sortable
     * @param string $align null(eq.left), 'center' or 'right'
     * @param <type> $renderFunc a function to callback to render Field with td, or an eval construction ($v,$r passed)
     * @param <type> $width
     */
    function  __construct ($field, $title, $sortable=true, $align=null, $renderFunc=null,
            $width=null){
        $this->field = $field;
        $this->title = $title;
        $this->isSortable = (bool) $sortable;
        $this->align = $align;
        $this->width = $width;
        if ($renderFunc)
            $this->setRenderFunction($renderFunc);
        if (!$this->renderFunc)
            $this->renderFunc = array($this, '_defaultRender');
        if (!$this->getFunc)
            $this->getFunc = array($this, '_defaultGet');
    }
    function init(Am_Grid_ReadOnly $grid)
    {
    }
    function renderTitle($controller)
    {
        $width_html = $this->width ? sprintf(' width="%s"', htmlentities($this->width, ENT_QUOTES, 'UTF-8')) : null;
        $sort_html = $this->isSortable() ? $controller->renderGridHeaderSortHtml($this) : array(null, null);
        $ret = '<th'.$width_html.'>'.$sort_html[0]. $this->title .$sort_html[1].'</th>';
        $this->applyDecorators('renderTitle', array(& $ret, $controller));
        return $ret;
    }
    function isSortable()
    {
        return $this->isSortable;
    }
    /** @return string value of the field - default or using "get" callback function */
    function get($obj, $controller, $field=null)
    {
        if (is_null($field)) {
            $field = $this->getFieldName();
        }
        $ret = call_user_func($this->getFunc, $obj, $controller, $field, $this);
        $this->applyDecorators('get', array(& $ret, $obj, $controller, $field));
        return $ret;
    }
    function render($obj, $controller){
        $ret = call_user_func($this->renderFunc, $obj, $this->field, $controller, $this);
        $this->applyDecorators('render', array(& $ret, $obj, $controller));
        return $ret;
    }
    function _defaultGet($obj, $controller, $field, $fieldObj)
    {
        return $obj->{$field};
    }
    function _defaultRender($obj, $field, $controller)
    {
        $val = $this->get($obj, $controller, $field);
        if ($this->formatFunc) $val = call_user_func($this->formatFunc, $val);
        $align_html = $this->align ? ' align="'.$this->align.'"' : null;
        return sprintf('<td%s>%s</td>', $align_html, htmlentities($val, null, 'UTF-8'));
    }
    function getFieldName()
    {
        return $this->field;
    }
    function getFieldTitle()
    {
        return $this->title;
    }
    function setWidth($width){
        $this->width = $width;
        return $this;
    }
    function setRenderFunction($renderFunc){
        if (is_string($renderFunc) && !is_callable($renderFunc) && strlen($renderFunc)) {
            $this->setGetFunction(create_function('$v', ' return ' . $renderFunc . ' ;'));
        } else {
            $this->renderFunc = $renderFunc;
        }
        return $this;
    }
    function setGetFunction($getFunc)
    {
        $this->getFunc = $getFunc;
        return $this;
    }
    function setFormatFunction($formatFunc)
    {
        $this->formatFunc = $formatFunc;
        return $this;
    }
    /**
     * @return string html/js/css that must not be reloaded between requests
     */
    function renderStatic()
    {
        $ret = "";
        $this->applyDecorators('renderStatic', array(& $ret));
        return $ret;
    }
    
    public function addDecorator(Am_Grid_Field_Decorator_Abstract $decorator)
    {
        $this->decorators[] = $decorator;
        $decorator->setField($this);
    }
    function applyDecorators($func, $args)
    {
        if (!$this->decorators) return;
        foreach ($this->decorators as $decorator)
            call_user_func_array(array($decorator, $func), $args);
    }
}
