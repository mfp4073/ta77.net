<?php

class Am_Grid_Field_Decorator_Shorten extends Am_Grid_Field_Decorator_Abstract
{
    protected $len;
    public function __construct($len)
    {
        $this->len = $len;
        parent::__construct();
    }
    public function render(&$out, $obj, $controller)
    {
        $out = preg_replace_callback('|(<td.*>)(.+)(</td>)|i', array($this, '_cb'), $out);
    }
    public function _cb($regs)
    {
        if (strlen($regs[2]) > $this->len)
        {
            $regs[2] = sprintf('<span title="%s">%s</span>', 
                htmlentities($regs[2], ENT_QUOTES, 'UTF-8'),
                substr($regs[2], 0, $this->len) . '...');
        }
        
        $val = $regs[1] . $regs[2] . $regs[3];
        return $val;
    }
}