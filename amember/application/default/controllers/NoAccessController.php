<?php

class NoAccessController extends Am_Controller
{
    /**
     * Use the following params from request
     * id, title
     */
    function liteAction() {
        $this->view->accessObjectTitle = $this->getParam('title', ___('protected area'));
        $this->view->orderUrl = REL_ROOT_URL . '/signup';
        $this->view->display('no-access.phtml');
    }
    
    function folderAction()
    {
        $id = $this->_request->getInt('id');
        if (!$id) throw new Am_Exception_InputError("Empty folder#");
        $folder = $this->getDi()->folderTable->load($id);
        if(empty($folder)) throw new Am_Exception_InputError("Folder not found");

        // Check if login cookie exists. If not, user is not logged in and should be redirected to login page.
        
        $pl = $this->getDi()->plugins_protect->loadGet('new-rewrite');
        
        // User will be there only if file related to folder doesn't exists. 
        // So if main file exists, this means that user is logged in but don't have an access. 
        // If main file doesn't exists, redirect user to new-rewrite in order to recreate it. 
        // Main file will be created even if user is not active. 
        
        if(is_file($pl->getFilePath($pl->getEscapedCookie())))
        {
            $this->view->accessObjectTitle = ___("Folder %s (%s)", $folder->title, $folder->url);
            $this->view->orderUrl = REL_ROOT_URL . '/signup';
            $this->view->display('no-access.phtml');
        }
        else
        {
             $url = sprintf("%s/protect/new-rewrite?f=%d&url=%s", 
                 REL_ROOT_URL, $id, 
                 $this->_request->getParam('url', $folder->getUrl()));
             Am_Controller::redirectLocation($url);
        }
    }
    function contentAction()
    {
        $id = $this->_request->getInt('id');
        $type = $this->_request->getFiltered('type');
        if (!$id) throw new Am_Exception_InputError("Empty folder#");
        $this->view->accessObjectTitle = ___("Protected Content [%s-%d]", $type, $id);
        $this->view->orderUrl = REL_ROOT_URL . '/signup';
        $this->view->display('no-access.phtml');
    }
}