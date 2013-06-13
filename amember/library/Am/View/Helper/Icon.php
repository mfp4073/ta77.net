<?php

/**
 * Display icon from sprite or from icons/$name.png file
 * @package Am_View
 */
class Am_View_Helper_Icon extends Zend_View_Helper_Abstract
{
    public function icon($name, $alt='', $source='icon')
    {
        $arr = is_string($alt) ? array('alt' => $alt, ) : (array)$alt;
        $attrs = "";
        foreach ($arr as $k => $v)
            $attrs .= $this->view->escape($k) . '="' . $this->view->escape($v) . '" ';
        
        $spriteOffset = Am_View::getSpriteOffset($name, $source);
        if ($spriteOffset !== false) {
            if (!empty($arr['alt']))
                $attrs .= ' title="' . $this->view->escape($arr['alt']) . '"';
            $res = sprintf('<div class="glyph sprite-%s" style="background-position: %spx center;" %s></div>',
                    $source, -1 * $spriteOffset, $attrs);
        } 
        elseif ($src = $this->view->_scriptImg('icons/' . $name . '.png')) 
        {
            $res = sprintf ('<img src="%s" '.$attrs.' />',
                $src);
        } 
        else 
        {
            if (!empty($arr['alt']))
                $res = $arr['alt'];
            else
                $res = null;
        }
        return $res;
    }
}

