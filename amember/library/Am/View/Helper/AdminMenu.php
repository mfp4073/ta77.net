<?php

/**
 * View helper to display admin menu
 * @package Am_View 
 */
class Am_View_Helper_AdminMenu extends Zend_View_Helper_Abstract {
    protected $activePageId = null;
    protected $acl = null;

    public function setAcl($acl)
    {
        $this->acl = $acl;
    }
    
    public function adminMenu() {
        return $this;
    }

    public function renderMenu(Zend_Navigation_Container $container, $options = array()) {
        $html = '';
        $pages = $container->getPages();
        foreach ($pages as $page) 
        {
            /* @var $page Zend_Navigation_Page */
            if ($this->acl && ($recources = $page->getResource())) {
                $hasPermission = false;
                foreach ((array)$recources as $recource) {
                    if ($this->acl->hasPermission($recource, $page->getPrivilege()))
                        $hasPermission = true;
                }
                if (!$hasPermission) continue;
            }

            if ($page->isActive() ) {
                $this->activePageId = $this->getId($page);
            }
            if (!$page->isVisible(true)) continue;
            if (!$page->getHref()) continue;
            $subMenu = $this->renderSubMenu($page);
            $class = $subMenu ? 'folder' : '';
            
            if (!($page->hasChildren() && !$subMenu)) {
                $html .= sprintf('<li><div class="menu-glyph"%s><div class="menu-glyph-delimeter"><a id="%s" href="%s" class="%s">%s</a></div></div>%s</li>',
                        $this->getInlineStyle($page->getId()),
                        'menu-' . $this->getId($page),
                        $page->hasChildren() ? 'javascript:;' : $page->getHref(),
                        $class . " " . $page->getClass(),
                        $this->view->escape($page->getLabel()),
                        $subMenu
                );
            }
        }

        $script= '';

        if ($this->activePageId) {
            $script = <<<CUT
<script type="text/javascript">
$(function(){
    $('.admin-menu').adminMenu('{$this->activePageId}');
});
</script>
CUT;
        }

        return sprintf('<ul class="admin-menu">%s</ul>%s%s',
                $html, "\n", $script);
    }

    protected function renderSubMenu(Zend_Navigation_Page $page) {
        $html = '';
        $pages = $page->getPages();
        foreach ($pages as $subPage) 
        {
            if ($this->acl && ($recources = $subPage->getResource())) {
                $hasPermission = false;
                foreach ((array)$recources as $recource) {
                    if ($this->acl->hasPermission($recource, $subPage->getPrivilege()))
                        $hasPermission = true;
                }
                if (!$hasPermission) continue;
            }
            if ($subPage->isActive()) {
                $this->activePageId = $this->getId($subPage);
            }
            if (!$subPage->isVisible(true)) continue;
            if (!$subPage->getHref()) continue;
            $html .= sprintf('<li><div class="menu-glyph" %s><a id="%s" href="%s" class="%s">%s</a></div></li>',
                    $this->getInlineStyle($subPage->getId(), 15),
                    'menu-' . $this->getId($subPage),
                    $subPage->getHref(),
                    $subPage->getClass(),
                    $this->view->escape($subPage->getLabel())
            );
        }
        return $html ? sprintf('<ul>%s</ul>', $html) : $html;
    }

    protected function getInlineStyle($id, $offset = 10) {

        $spriteOffset = Am_View::getSpriteOffset($id);
        if ($spriteOffset === false) return '';
        
        $realOffset = $offset - $spriteOffset;

        return sprintf(' style="background-position: %spx center;" ', $realOffset);
    }

    protected function getId(Zend_Navigation_Page $page) {
        $id = $page->getId();
        if (!empty($id)) return $id;
        if ($page instanceof Zend_Navigation_Page_Mvc)
            return sprintf('%s-%s', $page->getController(), $page->getAction());
        elseif ($page instanceof Zend_Navigation_Page_Uri)
            return crc32($page->getUri);
    }
}

