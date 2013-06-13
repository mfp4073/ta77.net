<?php

/**
 * An admin UI element to handle visual bricks configuration
 * 
 * @package Am_SavedForm
 */
class Am_Form_Element_BricksEditor extends HTML_QuickForm2_Element
{
    const ALL = 'all';
    const ENABLED = 'enabled';
    const DISABLED = 'disabled';
    
    protected $bricks = array();
    protected $value = array();
    /** @var Am_Form_Bricked */
    protected $brickedForm = null;

    public function __construct($name, $attributes, Am_Form_Bricked $form)
    {
        parent::__construct($name, $attributes, null);
        $this->brickedForm = $form;
        class_exists('Am_Form_Brick', true);
        foreach ($this->brickedForm->getAvailableBricks() as $brick)
            $this->bricks[$brick->getClass()][$brick->getId()] = $brick;
    }

    public function getType()
    {
        return 'hidden'; // we will output the row HTML too
    }

    public function getRawValue()
    {
        $value = array();
        foreach ($this->value as $row)
        {
            if ($brick = $this->getBrick($row['class'], $row['id']))
                $value[] = $brick->getRecord();
        }
        return Am_Controller::getJson($value);
    }

    public function setValue($value)
    {
        if (is_string($value))
            $value = Am_Controller::decodeJson($value);
        $this->value = (array)$value;
        foreach ($this->value as & $row)
        {
            if (empty($row['id']))
                continue;
            if (isset($row['config']) && is_string($row['config']))
            {
                parse_str($row['config'], $c);
                if (get_magic_quotes_gpc())
                    $c = Am_Request::ss($c); // remove quotes
                $row['config'] = $c;
            }
            if ($brick = $this->getBrick($row['class'], $row['id']))
            {
                $brick->setFromRecord($row);
            }
        }
        // handle special case - where there is a "multiple" brick and that is enabled
        // we have to insert additional brick to "disabled", so new bricks of same
        // type can be added in editor
        $disabled = $this->getBricks(self::DISABLED);
        foreach ($this->getBricks(self::ENABLED) as $brick)
        {
            if (!$brick->isMultiple()) continue;
            $found = false;
            foreach ($disabled as $dBrick)
                if ($dBrick->getClass() == $brick->getClass()) { $found = true; break;}; 
            // create new disabled brick of same class
            if (!$found) 
                $this->getBrick($brick->getClass(), null);
        }
    }

    /**
     * Clones element if necessary (if id passed say as "id-1" and it is not found)
     * @return Am_Form_Brick|null
     */
    public function getBrick($class, $id)
    {
        if 
        (  !isset($this->bricks[$class][$id])
            && isset($this->bricks[$class])
            && current($this->bricks[$class])->isMultiple()
        )
        {
            if ($id === null)
                for ($i = 0; $i<100; $i++)
                    if (!array_key_exists($class . '-' . $i, $this->bricks[$class]))
                    {
                        $id = $class . '-' . $i;
                        break;
                    }
            $this->bricks[$class][$id] = Am_Form_Brick::createFromRecord(array('class' => $class, 'id' => $id));
        }
        return empty($this->bricks[$class][$id]) ? null : $this->bricks[$class][$id];
    }
    public function getBricks($where = self::ALL)
    {
        $enabled = array();
        foreach ($this->value as $row)
            if (!empty($row['id']))
                $enabled[ ] = $row['id'];
        
        $ret = array();
        foreach ($this->bricks as $class => $bricks)
            foreach ($bricks as $id => $b)
            {
                if ($where == self::ENABLED && !in_array($id, $enabled))
                    continue;
                if ($where == self::DISABLED && in_array($id, $enabled))
                    continue;
                $ret[$id] = $b;
            }
        // if we need enabled element, we need to maintain order according to value
        if ($where == self::ENABLED)
        {
            $ret0 = $ret;
            $ret = array();
            foreach ($enabled as $id)
                if (isset($ret0[$id]))
                    $ret[$id] = $ret0[$id];
        }
        return $ret;
    }

    public function render(HTML_QuickForm2_Renderer $renderer)
    {
        $renderer->getJavascriptBuilder()->addLibrary('bricks-editor', 'bricks-editor.js', 
            REL_ROOT_URL . '/application/default/views/public/js');
        return parent::render($renderer);
    }

    public function __toString()
    {
        $enabled = $disabled = "";
        foreach ($this->getBricks(self::ENABLED) as $brick)
            $enabled .= $this->renderBrick($brick, true) . "\n";
        foreach ($this->getBricks(self::DISABLED) as $brick)
            $disabled .= $this->renderBrick($brick, false) . "\n";
        
        $hidden = is_string($this->value) ? $this->value : Am_Controller::getJson($this->value);
        $hidden = Am_Controller::escape($hidden);
        
        $name = $this->getName();
        $formBricks = ___("Form Bricks");
        $availableBricks = ___("Available Bricks (drag to left to add)");
        $comments = nl2br(
            ___("To add fields into the form, move item from 'Available Bricks' to 'Form Bricks'.\n".
            "To remove fields, move it back to 'Available Bricks'.\n".
            "To make form multi-page, insert 'PageSeparator' item into the place where you want page to be split.")
           );
        
        
        return $this->getCss() . <<<CUT
<input type="hidden" name="$name" value="$hidden">
<div class='header-signup-form'>$formBricks</div>
<div class='header-available-fields'>$availableBricks</div>
<br clear='all' />

<ul id='bricks-enabled' class='connectedSortable'>
$enabled
</ul>
<ul id='bricks-disabled' class='connectedSortable'>
$disabled
</ul>
<div style='clear: both'></div>
<div class='brick-comment'>$comments</div>
CUT;
    }

    public function renderConfigForms()
    {
        $out = "<!-- brick config forms -->";
        foreach ($this->getBricks(self::ALL) as $brick)
        {
            if (!$brick->haveConfigForm())
                continue;
            $form = new Am_Form_Admin;
            $brick->initConfigForm($form);
            $form->setDataSources(array(new Am_Request($brick->getConfigArray())));
            $out .= "<div id='brick-config-{$brick->getId()}' class='brick-config' style='display:none'>\n";
            $out .= "<h1>".___("%s Configuration", $brick->getName())."</h1>";
            $out .= (string) $form;
            $out .= "</div>\n\n";
        }
        
        $form = new Am_Form_Admin;
        $form->addElement('textarea', '_tpl', array('rows' => 1, 'cols' => 40))->setLabel('-label-');
        $out .= "<div id='brick-labels' style='display:none'>\n";
        $out .= "<h1>".___('Edit Brick Labels')."</h1>";
        $out .= (string)$form;
        $out .= "</div>\n";
        $out .= "<!-- end of brick config forms -->";
        return $out;
    }

    public function renderBrick(Am_Form_Brick $brick, $enabled)
    {
        $class = $enabled ? 'ui-state-default' : 'ui-state-default';
        $configure = $labels = null;
        $attr = array(
            'id' => $brick->getId(),
            'class' => "brick $class " . $brick->getClass(),
            'data-class' => $brick->getClass(),
        );
        if ($brick->haveConfigForm()) 
        {
            $attr['data-config'] = Am_Controller::getJson($brick->getConfigArray());
            $configure = "<a class='configure'>configure...</a>";
        }
        if ($brick->getStdLabels())
        {
            $attr['data-labels'] = Am_Controller::getJson($brick->getCustomLabels());
            $attr['data-stdlabels'] = Am_Controller::getJson($brick->getStdLabels());
            $class = $brick->getCustomLabels() ? 'labels custom-labels' : 'labels';
            $labels = "<a class='$class'>labels...</a>";
        }
        
        if ($brick->isMultiple())
            $attr['data-multiple'] = "1";
        
        if ($brick->hideIfLoggedInPossible() == Am_Form_Brick::HIDE_DESIRED)
            $attr['data-hide'] = $brick->hideIfLoggedIn() ? 1 : 0;
        
        
        $attrString = "";
        foreach ($attr as $k => $v)
            $attrString .= " $k=\"".Am_Controller::escape($v)."\"";
        
        $checkbox = $this->renderHideIfLoggedInCheckbox($brick);
        return "<div $attrString>
        {$brick->getName()}
        $configure
        $labels
        $checkbox
        </div>";
    }
    
    protected function renderHideIfLoggedInCheckbox(Am_Form_Brick $brick)
    {
        if (($this->brickedForm instanceof Am_Form_Signup) 
            // do not display checkboxes if that form is JUST for signup and not for payment
            && (!empty($this->bricks['product']) || !empty($this->bricks['paysystem'])))
        {
            if ($brick->hideIfLoggedInPossible() != Am_Form_Brick::HIDE_DONT)
            {
                static $checkbox_id = 0;
                $checkbox_id++;
                $checked = $brick->hideIfLoggedIn();
                if ($brick->hideIfLoggedInPossible() == Am_Form_Brick::HIDE_ALWAYS)
                {
                    $checked = "checked='checked'";
                    $disabled = "disabled='disabled'";
                } else {
                    $disabled = "";
                    $checked = $brick->hideIfLoggedIn() ? "checked='checked'" : '';    
                }
                return 
                    "<span class='hide-if-logged-in'><input type='checkbox'".
                    " id='chkbox-$checkbox_id' value=1 $checked $disabled />" .
                    "<label for='chkbox-$checkbox_id'>(hide if logged-in)</label></span>\n";
            }
        }
    }

    public function getCss()
    {
        $id = $this->getId();
        return <<<CUT
<style type="text/css">
    #bricks-enabled, #bricks-disabled  { width: 45%; padding: 10px; float: left; }
    .brick { border: solid 1px lightgray; margin: 2px; padding: 1px; }
    .page-separator { margin-top: 10px; background-color: lightgreen; }
    .header-signup-form, .header-available-fields { width: 45%; padding: 10px; float: left; font-size: 100%; font-weight: bold; }    .brick-comment { padding: 10px; font-size: 100%; }
    .hide-if-logged-in { margin-left: 20px; }
    
    
    #bricks-disabled .hide-if-logged-in { display: none; }
    #bricks-disabled a.configure { display: none; }
    #bricks-disabled a.labels { display: none; }
    a.configure, a.labels { color: blue; }
    a.labels.custom-labels { color: #360; }
    
    #row-$id .element-title { display: none; }
    #row-$id .element { margin-left: 0; }
</style>
CUT;
    }

}
