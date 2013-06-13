<?php

class Am_Storage_S3 extends Am_Storage
{
    protected $_connector;
    /** last used bucket */
    protected $_bucket;
    protected $cacheLifetime = 300; // 5 minutes
    
    public function isConfigured()
    {
        return $this->getConfig('secret_key') && $this->getConfig('access_key');
    }
    public function onSetupForms(Am_Event_SetupForms $event)
    {
        $form = new Am_Form_Setup($this->getId());
        $event->addForm($form);

        $form->setTitle('Amazon S3');
        
        $form->addText('access_key', array('size' => 40))->setLabel('AWS Access Key')
            ->addRule('required')
            ->addRule('regex', 'must be alphanumeric', '/^[A-Z0-9]+$/');
        $form->addPassword('secret_key', array('size' => 40))->setLabel('AWS Secret Key')
            ->addRule('required');
        $form->addText('expire', array('size' => 5))->setLabel('Video link lifetime, min');
        $form->setDefault('expire', 15);
        $form->addFieldsPrefix('storage.s3.');
    }
    public function getDescription()
    {
        if ($this->isConfigured())
            return ___("Files located on Amazon S3 storage. (Warning: Your buckets should not contain letters in uppercase in its name)");
        else
            return ___("Amazon S3 storage is not configured");
    }
    /** @return S3 */
    protected function getConnector()
    {
        if (!$this->_connector)
        {
            $this->_connector = new S3($this->getConfig('access_key'), $this->getConfig('secret_key'));
            $this->_connector->setRequestClass('S3Request_HttpRequest2');
        }
        return $this->_connector;
    }
    /** @access private testing */
    public function _setConnector($connector)
    {
        $this->_connector = $connector;
    }
    public function getItems($path, array & $actions)
    {
        $items = array();
        if ($path == '')
        {
            $buckets = $this->getDi()->cacheFunction->call(
                array($this->getConnector(), 'listBuckets'), 
                array(), array(), $this->cacheLifetime);
            foreach ($buckets as $name)
                $items[] = new Am_Storage_Folder($this, $name, $name);
            
            $actions[] = new Am_Storage_Action_Refresh($this, '');
            
        } else {
            $items[] = new Am_Storage_Folder($this, '..', '');
            
            @list($bucket, $bpath) = explode('/', $path, 2);
            $ret = $this->getDi()->cacheFunction->call(
                array($this->getConnector(), 'getBucket'), 
                array($bucket, null/*$bpath*/, null, 300/*, $delimiter = '/'*/), array(), $this->cacheLifetime);
            
            $this->_bucket = $bucket;
            foreach ($ret as $r)
            {
                $items[] = $item = new Am_Storage_File($this, $r['name'], $r['size'], 
                    $bucket . '/' . $r['name'],
                    null, null);
                $item->_hash = $r['hash'];
            }
            
            $actions[] = new Am_Storage_Action_Refresh($this, $path);
//            $actions[] = $x = new Am_Storage_Action_Upload($this, $this->getId() . '::' .$bucket, 
//                $this->renderUpload($bucket));
        }
        return $items;
    }
    
    public function isLocal()
    {
        return false;
    }
    public function get($path)
    {
        list($bucket, $uri) = explode('/', $path, 2);
        $info = $this->getDi()->cacheFunction->call(
                array($this->getConnector(), 'getObjectInfo'), 
                array($bucket, $uri), array(), $this->cacheLifetime);

        $p = preg_split('|[\\\/]|', $path); // get name
        $name = array_pop($p);
        return new Am_Storage_File($this, $name, $info['size'], $path, $info['type'], null);
    }
    
    public function getUrl(Am_Storage_File $file, $expTime)
    {
        list($bucket, $uri) = explode('/', $file->getPath(), 2);
        return $this->getConnector()->getAuthenticatedURL($bucket, $uri, $expTime);
    }
/*
 * <PostResponse>
 *   <Location>https://amember-com.s3.amazonaws.com/filename.jpg</Location>
 *   <Bucket>xxx-com</Bucket>
 *   <Key>fn.jpg</Key>
 *   <ETag>"123ad031affb55f5b5a1da5f12a42cbf"</ETag>
 * </PostResponse>
 */        
    
    public function action(array $query, $path, $url, Am_Request $request, Zend_Controller_Response_Http $response)
    {
        switch ($query['action'])
        {
            case 'refresh':
                $this->getDi()->cacheFunction->clean();
                $response->setRedirect($url);
                break;
            default:
                throw new Am_Exception_InputError('unknown action!');
        }
    }
    
//    protected function renderUpload($bucket)
//    {
//        $output = "";
//        $output .= "<p>Upload file to Amazon S3</p>";
//        $bucket = Am_Controller::escape($bucket);
//        $output .= "<form enctype='multipart/form-data' action='https://$bucket.s3.amazonaws.com/' method='post'>";
//        $output .= Am_Controller::renderArrayAsInputHiddens( $x = 
//            $this->getConnector()->getHttpUploadPostParams($bucket, '', S3::ACL_PRIVATE, 
//                3600, 1024*1024*30)
//        );
//        $output .= "<input type='file' name='file' />";
//        $output .= "<input type='submit' value='Upload' /></form>";
//        return $output;
//    }
}