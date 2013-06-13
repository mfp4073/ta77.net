<?php
/*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.amember.com/
*    Release: 4.2.17
*    License: Commercial http://www.amember.com/p/main/License/
*/

/**
 * Admin dashboard widget class
 * @package Am_Utils 
 */
class Am_Widget 
{
    const TARGET_TOP = 'top';
    const TARGET_BOTTOM = 'bottom';
    const TARGET_MAIN = 'main';
    const TARGET_ASIDE = 'aside';
    const TARGET_ANY = -1;

    protected $id, $title, $renderCallback, $targets, $configForm, $permission, $invokeArgs;

    function  __construct($id, $title, $renderCallback, $targets, $configForm = null, $permission=null, $invokeArgs = array())
    {
            $this->id = $id;
            $this->title = $title;
            $this->renderCallback = $renderCallback;
            $this->targets = $targets == self::TARGET_ANY ? array(
                self::TARGET_MAIN, self::TARGET_TOP, self::TARGET_BOTTOM, self::TARGET_ASIDE
            ) : $targets;
            $this->configForm = $configForm;
            $this->permission = $permission;
            $this->invokeArgs = $invokeArgs;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTargets()
    {
        return $this->targets;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function render(Am_View $view, $config=null) {
        return call_user_func($this->renderCallback, $view, $config, $this->invokeArgs);
    }

    public function hasPermission(Admin $admin)
    {
        return $admin->hasPermission($this->permission);
    }

    public function hasConfigForm()
    {
        return !is_null($this->configForm);
    }

    public function getConfigForm()
    {
        $form = is_callable($this->configForm) ? call_user_func($this->configForm) : $this->configForm;
        if ($form) {
            $form->addHidden('id')->setValue($this->getId())->toggleFrozen(true);
        }
        return $form;
    }
}