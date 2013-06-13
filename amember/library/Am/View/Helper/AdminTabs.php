<?php

/**
 * View helper to display admin tabs 
 * @package Am_View
 */
class Am_View_Helper_AdminTabs extends Zend_View_Helper_Abstract
{
    function adminTabs(Zend_Navigation $menu)
    {
        $m = new Am_View_Helper_Menu();
        $m->setView($this->view);
        //$m->setAcl($this->view->di->authAdmin->getUser());
        $out = '<div class="tabs">';
        $out .= $m->renderMenu($menu,
            array(
                'ulClass' => '',
                'activeClass' => 'active',
                'normalClass' => 'normal',
                'disabledClass' => 'disabled',
                'maxDepth' => 1,
            )
        );
        $out .= '</div>';
        return $out;
    }
}