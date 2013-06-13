<?php

/**
 * A Zend_Controller_Front plugin, handles admin authentication and maintanance
 * mode, checks if requested module is enabled
 * 
 * @package Am_Controller 
 */
class Am_Controller_Plugin extends Zend_Controller_Plugin_Abstract
{
    private $di;
    public function __construct(Am_Di $di)
    {
        $this->di = $di;
    }
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        // check if we need to handle admin auth
        if (stripos($this->getRequest()->getControllerName(), 'admin')===0)
        {
            defined('AM_ADMIN') || define('AM_ADMIN', true);
            if (($this->di->authAdmin->getUserId() <= 0)
                && $request->getControllerName() != 'admin-auth')
            {
                $request->setControllerName('admin-auth')->setActionName('index')->setModuleName('default');
            }
        // check for maintenance mode
        } 
        elseif ($msg = $this->di->config->get('maintenance'))
        {
            if (!$this->di->authAdmin->getUserId())
                return amMaintenance($msg);
        }
        // check if we are accessing disabled module
        $module = $request->getModuleName();
        if ($module != 'default')
        {
            if (!$this->di->modules->isEnabled($module))
                throw new Am_Exception_InputError("You are trying to access disabled module [" . htmlentities($module) . ']');
        }
    }
}

