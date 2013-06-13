<?php

class ContentController extends Am_Controller
{
    
    /** @access private for unit testing */
    public function _setHelper($v)
    {
        $this->_helper->addHelper($v);
    }
    /**
     * Serve file download
     */
    function fAction()
    {
        $f = $this->loadWithAccessCheck($this->getDi()->fileTable, $id = $this->getInt('id'));
        // download limits works for user access only and not for guest access
        if ($this->getDi()->auth->getUserId())
        {
            if (!$this->getDi()->fileDownloadTable->checkLimits($this->getDi()->auth->getUser(), $f)) {
                throw new Am_Exception_AccessDenied(___("Download limit exceeded for this file"));
            }

            $this->getDi()->fileDownloadTable->logDownload($this->getDi()->auth->getUser(), $f, $this->getRequest()->getClientIp());
        }
        if ($path = $f->getFullPath())
        {
            @ini_set('zlib.output_compression', 'Off'); // for IE
            if (!file_exists($path))
                throw new Am_Exception_InternalError("File [$id] was not found on disk. Path [$f->path]");
            $this->_helper->sendFile($path, $f->getMime(), 
                array(
                    //'cache'=>array('max-age'=>3600),
                    'filename' => $f->getDisplayFilename(),
            ));
        } else
            $this->redirectLocation($f->getProtectedUrl(600));
    }
    
    /**
     * Display saved page
     */
    function pAction()
    {
        $p = $this->loadWithAccessCheck($this->getDi()->pageTable, $this->getInt('id'));
        
        if (strpos($p->html, '%user.') !== null)
        {
            $t = new Am_SimpleTemplate();
            if ($this->getDi()->auth->getUserId())
                $t->assign('user', $this->getDi()->user);
            $t->assignStdVars();
            $p->html = $t->render($p->html);
            
        }
        
        if ($p->use_layout)
        {
            $this->view->content = '<div class="am-content-page">' . $p->html . '</div>';
            $this->view->title = $p->title;
            $this->view->display('layout.phtml');
        } else 
            echo $p->html;
    }
    function loadWithAccessCheck(ResourceAbstractTable $table, $id)
    {
        $id = $this->getInt('id');
        if ($id<=0)
            throw new Am_Exception_InputError(___("Wrong link - no id passed"));
        
        $p = $table->load($id);
        if (!$this->getDi()->auth->getUserId()) // not logged-in
        {
            if ($p->hasAccess(null)) // guest access allowed?
                return $p;           // then process
            $this->_redirect('login?amember_redirect_url=' . $this->getFullUrl());
        }
        if (!$p->hasAccess($this->getDi()->user))
            $this->_redirect('no-access/content/'.sprintf('?id=%d&type=%s', 
                $id, $table->getName(true)));
        
        return $p;
    }
}
