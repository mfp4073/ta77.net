<?php
/*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.amember.com/
*    Release: 4.2.17
*    License: LGPL http://www.gnu.org/copyleft/lesser.html
*/

/**
 * Abstract grid filter class
 * @package Am_Grid
 */
abstract class Am_Grid_Filter_Abstract implements Am_Grid_Filter_Interface
{
    /** @var Am_Grid_ReadOnly */
    protected $grid;
    protected $gridId = "";
    protected $title = "";
    protected $buttonTitle = "";
    protected $varList = array(
        'filter'
    );
    // for text input
    protected $attributes = array();
    protected $vars = array();
    
    public function __construct()
    {
    }
    
    public function getVariablesList()
    {
        return $this->varList;
    }
    public function initFilter(Am_Grid_ReadOnly $grid)
    {
        if (empty($this->title))
            $this->title = ___("Filter");
        if (empty($this->buttonTitle))
            $this->buttonTitle = ___("Apply");
        $this->grid = $grid;
        $this->vars = array();
        foreach ($this->varList as $k)
            $this->vars[$k] = $this->grid->getRequest()->get($k);
        if ($this->isFiltered())
            $this->applyFilter();
    }
    /** apply filter using $this->vars array */
    abstract protected function applyFilter();
    
    public function isFiltered()
    {
        foreach ($this->vars as $k => $v) 
            if (!empty($v)) 
                return true;
        return false;
    }
    public function getTitle()
    {
        return $this->title;
    }

    protected function getParam($name, $default=null) {
        return isset($this->vars[$name]) ? $this->vars[$name] : $default;
    }

    public function getAllButFilterVars()
    {
        $ret = array();
        $prefix = $this->grid->getId() . '_';
        foreach ($this->grid->getCompleteRequest()->toArray() as $k => $v)
        {
            if (strpos($k, $prefix)!==false)
            {
                $kk = substr($k, strlen($prefix));
                if (in_array($kk, $this->getVariablesList())) continue;
                if ($kk == 'p') continue; // skip page# too we do not want to see empty list
            }
            $ret[$k] = $v;
        }
        return $ret;
    }
    public function renderFilter()
    {
        $action = 
            htmlentities($this->grid->getCompleteRequest()->getBaseUrl() .
            $this->grid->getCompleteRequest()->getPathInfo(), ENT_QUOTES, 'UTF-8');
        $title = $this->getTitle();
        $vars = $this->getAllButFilterVars();
        $hiddenInputs = Am_Controller::renderArrayAsInputHiddens($vars);
        $inputs = $this->renderInputs();
        $button = $this->renderButton();
        return <<<CUT
<div class="filter-wrap">
    <form class="filter" method="get" action="$action">
        <div class="filter-button">
        $button
        </div>
        <div class="filter-inputs">
        $hiddenInputs
        $inputs
        </div>
        <div class="filter-title">$title</div>
    </form>
</div>                
CUT;
    }
    abstract function renderInputs();
    protected function renderButton()
    {
        return sprintf('<input type="submit" value="%s" class="gridFilterButton" />',
            htmlentities($this->buttonTitle, ENT_QUOTES, 'UTF-8'));
    }
    function renderStatic() {}
    
    function renderInputText($name = 'filter')
    {
        $attrs = $this->attributes;
        $attrs["name"] = $this->grid->getId() . '_' . $name;
        $attrs["type"] = "text";
        
        if (!isset($attrs['value']))
            $attrs["value"] = $this->vars[$name];
        
        $out = "<input";
        foreach ($attrs as $k => $v)
            $out .= ' ' . $k . '="' . htmlentities($v, ENT_QUOTES, 'UTF-8') . '"';
        $out .= " />";
        return $out ;
    }

    function renderInputCheckboxes($name, $options)
    {
        $attrs = $this->attributes;
        $attrs["name"] = $this->grid->getId() . '_' . $name . '[]';
        $attrs["type"] = "checkbox";

        $out = '';
        foreach ($options as $k=>$title) {
            $attrs['value'] = $k;
            if (in_array($k, $this->getParam($name, array()))) {
                $attrs['checked'] = 'checked';
            } else {
                unset($attrs['checked']);
            }
            $out .= sprintf(' <input %s> %s', $this->renderAttributes($attrs), htmlentities($title, ENT_QUOTES, 'UTF-8'));
        }

        return $out ;
    }

    function renderInputSelect($name, $options, $attributes = array())
    {
        $out = '';

        foreach ($options as $value => $title) {
            $out .= sprintf('<option value="%s"%s>%s</option>',
                        htmlentities($value, ENT_QUOTES, 'UTF-8'),
                        (($value == $this->getParam($name)) ? ' selected="selected"' : ''),
                        htmlentities($title, ENT_QUOTES, 'UTF-8')
                    );
        }

        $out = sprintf('<select name="%s"%s>%s</select>',
                    $this->grid->getId() . '_' . $name,
                    $this->renderAttributes($attributes),
                    $out
                );

        return $out ;
    }

    function renderAttributes($attributes) {
        $out = '';
        foreach ($attributes as $k => $v)
            $out .= ' ' . $k . '="' . htmlentities($v, ENT_QUOTES, 'UTF-8') . '"';

        return $out ;
    }
}