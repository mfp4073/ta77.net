<?php

abstract class MediaController extends Am_Controller
{
    protected $id;
    protected $media;
    protected $type;
    abstract function getFlowplayerParams(ResourceAbstractFile $media);

    function getMedia()
    {
        if (!$this->media)
        {
            $this->id = $this->_request->getInt('id');
            if (!$this->id)
                throw new Am_Exception_InputError("Wrong URL - no media id passed");
            $this->media = $this->getDi()->videoTable->load($this->id, false);
            if (!$this->media)
                throw new Am_Exception_InputError("This media has been removed");
        }
        return $this->media;
    }

    function sendByRange($path, $mime){
        $filesize = filesize($path);

        preg_match('/bytes=(.*)/', $_SERVER['HTTP_RANGE'], $matches);
        list($first_byte, $last_byte) = explode('-', $matches[1]);
        
        $first_byte =  intval($first_byte);
        $last_byte = min(($filesize-1), ($last_byte ? intval($last_byte) : ($filesize-1)));
        
        $length = $last_byte - $first_byte + 1;

        $file = fopen($path, 'r');
        fseek($file, $first_byte);

        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $first_byte . '-' . $last_byte . '/' . $filesize);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $length);
        header('Accept-Ranges: bytes');
        
        $chunk = 1024*1024;
        for($i=$first_byte; $i<$last_byte; $i+=$chunk)
            print fread($file, min($chunk,$last_byte-$i+1));
        fclose($file);
    }

    function dAction()
    {
        $id = $this->_request->get('id');
        $this->validateSignedLink($id);
        $id = intval($id);
        $media = $this->getDi()->videoTable->load($id);
        set_time_limit(600);

        while (@ob_end_clean());

        
        if ($path = $media->getFullPath())
        {
            $mime = $media->mime ? $media->mime : 'application/octet-stream';
            if(isset($_SERVER['HTTP_RANGE'])){
                $this->sendByRange($path, $mime);
            }else{
                header("Content-type: $mime");
               

                // @todo use X-SendFile where possible
                readfile($media->getFullPath());
            }
        } else
            $this->redirectLocation($media->getProtectedUrl($this->getDi()->config->get('storage.s3.expire', 15) * 60));
    }

    function pAction()
    {
        $this->view->title = $this->getMedia()->title;
        $this->view->content =
            "<script type='text/javascript' id='am-{$this->type}-{$this->id}'>" .
            $this->renderJs() .
            "\n</script>";
        $this->view->display('layout.phtml');
    }

    function getSignedLink(ResourceAbstract $media)
    {
        $rel = $media->pk().'-'. ($this->getDi()->time + 3600*24);
        return ($this->getRequest()->isSecure() ? ROOT_SURL : ROOT_URL) . '/' . $this->type . '/d/id/'.
            $rel . '-' .
            $this->getDi()->app->getSiteHash('am-' . $this->type . '-'.$rel, 10);
    }

    function validateSignedLink($id)
    {
        @list($rec_id, $time, $hash) = explode('-', $id, 3);
        if ($rec_id<=0)
            throw new Am_Exception_InputError("Wrong media id#");
        if ($time < Am_Di::getInstance()->time)
            throw new Am_Exception_InputError("Media Link Expired");
        if ($hash != $this->getDi()->app->getSiteHash("am-" . $this->type . "-$rec_id-$time", 10))
            throw new Am_Exception_InputError("Media Link Error - Wrong Sign");
    }

    function renderJs()
    {
        $params = $this->getFlowplayerParams($this->getMedia());
        $this->view->id = $this->id;
        $this->view->type = $this->type;
        $this->view->width = $this->_request->getInt('width', isset($params['width']) ? $params['width'] : 520);
        $this->view->height = $this->_request->getInt('height', isset($params['height']) ? $params['height'] : 330);
        unset($params['width']);
        unset($params['height']);

        $guestAccess = false;
        $media = $this->getMedia();
        if (!$this->getDi()->auth->getUserId() && !($guestAccess=$media->hasAccess(null)))
        {
            $this->view->error = ___("You must be logged-in to open this media");
            $this->view->link  = ROOT_SURL . "/login";
        } elseif (!$guestAccess && !$media->hasAccess($this->getDi()->user)) {
            $this->view->error = ___("Your subscription does not allow access to this media");
            $this->view->link = REL_ROOT_URL . sprintf('/no-access/content/'. '?id=%d&type=%s',
                $media->pk(), $media->getTable()->getName(true));
             ROOT_SURL . "/member";
        } else {
            $this->view->flowPlayerParams = array_merge(array('key' => $this->getDi()->config->get('flowplayer_license')), $params);
            $this->view->media = $this->getSignedLink($media);
        }
        $this->view->isSecure = $this->getRequest()->isSecure();
        return $this->view->render('_media.flowplayer.phtml');
    }

    function jsAction()
    {
        $this->_response->setHeader('Content-type', 'text/javascript');
        $this->getMedia();
        echo $this->renderJs();
    }
}