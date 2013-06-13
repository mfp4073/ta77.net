<?php

class Am_Grid_Field_Expandable extends Am_Grid_Field
{
    protected $maxLength = 15;
    protected $placeholder = "Click to Expand";
    protected $isHtml = false;
    
    const PLACEHOLDER_SELF_TRUNCATE_BEGIN = 'placeholder-self-truncate-begin';
    const PLACEHOLDER_SELF_TRUNCATE_END = 'placeholder-self-truncate-end';

    public function __construct($field, $title, $sortable = false, $align = null, $renderFunc = null, $width = null)
    {
        $this->setRenderFunction(array($this, 'renderExpandable'));
        $this->setGetFunction(array($this, 'expandableGet'));
        parent::__construct($field, $title, $sortable, $align, $renderFunc, $width);
    }

    /**
     *
     * @param int $maxLength
     * @return Am_Grid_Field_Expandable
     */
    public function setMaxLength($maxLength)
    {
        $this->maxLength = (int) $maxLength;
        return $this;
    }

    /**
     *
     * @param string $placeholder
     * @return Am_Grid_Field_Expandable
     */
    public function setPlaceholder($placeholder)
    {
        $this->placeholder = $placeholder;
        return $this;
    }
    
    public function renderExpandable($obj, $field, $controller)
    {
        $val = $this->get($obj, $controller, $field);
        $isHtml = $this->isHtml;
        $out = '';
        if (strlen($val) <= $this->maxLength)
        {
            return $this->_defaultRender($obj, $field, $controller);
        } else
        {
            $align_class = $this->align ? ' align_' . $this->align : null;
            $placeholder = $this->getPlaceholder($val, $obj);

            $out .= '<td class="expandable';
            $out .= $align_class;
            $out .= '" >';
            $out .= '<div class="arrow"></div>';
            $out .= '<div class="placeholder">';
            $out .= $placeholder;
            $out .= '</div>'.PHP_EOL;
            $out .= '<input type="hidden" class="data';
            $out .= ( $isHtml ? ' isHtml' : '');
            $out .= '" value="' . $controller->escape($val) . '">'.PHP_EOL;
            $out .= '</td>';
        }
        return $out;
    }

    /**
     *
     * @param string $val
     * @return string html code of placeholder
     */
    protected function getPlaceholder($val, $obj)
    {
        if (is_null($this->placeholder) || $this->placeholder == self::PLACEHOLDER_SELF_TRUNCATE_END) {
            $placeholder = htmlentities(substr($val, 0, $this->maxLength), null, 'UTF-8') . ( strlen($val) > $this->maxLength ? '&hellip;' : '');
        } elseif ($this->placeholder == self::PLACEHOLDER_SELF_TRUNCATE_BEGIN) {
            $placeholder = ( strlen($val) > $this->maxLength ? '&hellip;' : '') . htmlentities(substr($val, -1 * $this->maxLength), null, 'UTF-8');
        } elseif(is_callable($this->placeholder)) {
            $placeholder = call_user_func($this->placeholder, $val, $obj);
        } else {
            $placeholder = htmlentities($this->placeholder, null, 'UTF-8');
        }

        return $placeholder;
    }


    public function expandableGet($obj, $controller, $field)
    {
        $val = $obj->{$field};
        if (!$val) return $val;
        // try unserialize
        if (is_string($val))
        {
            if (($x = @unserialize($val)) !== false)
                $val = $x;
        }
        switch (true)
        {
            case is_array($val) : 
                $out = '';
                foreach ($val as $k => $v)
                    $out .= $k . ' = ' . ((is_array($v)) ? print_r($v, true) : (string) $v) . PHP_EOL;
                return $out;
            case is_object($val) : 
                return get_class($val) . "\n" . (string) $obj;
        }
        return $val;
    }
    public function setEscape($flag = null)
    {
        $ret = ! $this->isHtml;
        if ($flag !== null) $this->isHtml = ! $flag;
        return $ret;
    }
    public function renderStatic()
    {
        $url = htmlentities(REL_ROOT_URL . "/application/default/views/public/js/htmlreg.js", ENT_QUOTES, 'UTF-8');
        return parent::renderStatic() . 
        '    <script type="text/javascript" src="'.$url.'"></script>' . PHP_EOL;
    }
}
