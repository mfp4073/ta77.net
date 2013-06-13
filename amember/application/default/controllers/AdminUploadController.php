<?php
/*
* 
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Admin Info / PHP
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision: 4883 $)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class_exists('Am_Form', true);

class AdminUploadController extends Am_Controller 
{

    public function checkAdminPermissions(Admin $admin) 
    {
        return true;
    }

    public function gridAction() 
    {
        $prefix = $this->getRequest()->getParam('prefix');
        $secure = $this->getRequest()->getParam('secure');
        if (!$prefix) {
            throw new Am_Exception_InputError('prefix is undefined');
        }   
        if (!$this->getDi()->uploadAcl->checkPermission($prefix, 
                    Am_Upload_Acl::ACCESS_LIST, 
                    $this->getDi()->authAdmin->getUser())) {
            throw new Am_Exception_AccessDenied();
        }
        
        if (in_array($prefix, array('downloads', 'video', 'personal-content')))
        {
            list($storageId, $path, $query) = $this->getDi()->plugins_storage->splitPath($this->_request->get('path', 'upload::'));
            $storagePlugins = $this->getDi()->plugins_storage->loadEnabled()->getAllEnabled();
        } else {
            list($storageId, $path, $query) = $this->getDi()->plugins_storage->splitPath($this->_request->get('path', 'upload::'));
            $storageId = 'upload';
            $path = 'upload::';
            $storagePlugins = array($this->getDi()->plugins_storage->loadGet($storageId));
        }
        
        $storage = $this->getDi()->plugins_storage->loadGet($storageId);
        $storage->setPrefix($prefix);
        $grid = new Am_Storage_Grid($storage, $this->_request, $this->_response, $storagePlugins);
        $grid->setSecure($secure);
        if ($query)
            $grid->action($query, $path, $this->view);
        else
            $grid->render($path, $this->view);
    }

    public function getAction() {

        $file = $this->getDi()->plugins_storage->getFile($this->getParam('id'));

        if (!$file) {
            throw new Am_Exception_InputError(
            'Can not fetch file for id: ' . $this->getParam('id')
            );
        }
//        @todo detect if file is upload and then check permissions
//        if (!$this->getDi()->uploadAcl->checkPermission($file,
//                    Am_Upload_Acl::ACCESS_READ,
//                    $this->getDi()->authAdmin->getUser())) {
//            throw new Am_Exception_AccessDenied();
//        }
        
        if ($path = $file->getLocalPath())
            $this->_helper->sendFile($path, $file->getMime(),
                array(
                    //'cache'=>array('max-age'=>3600),
                    'filename' => $file->getName(),
            ));
        else
            $this->redirectLocation($file->getUrl());
        exit;
    }

    protected function getUploadIds(Am_Upload $upload) {
        $upload_ids = array();
        foreach($upload->getUploads() as $upload) {
            $upload_ids[] = $upload->pk();
        }
        return $upload_ids;
    }

    public function reUploadAction() {
        $file = $this->getDi()->uploadTable->load($this->getParam('id'));
        if (!$this->getDi()->uploadAcl->checkPermission($file,
                    Am_Upload_Acl::ACCESS_WRITE,
                    $this->getDi()->authAdmin->getUser())) {
            throw new Am_Exception_AccessDenied();
        }

        $upload = new Am_Upload($this->getDi());

        try {
            $upload->processReSubmit('upload', $file);

            if ($file->isValid()) {
                $data = array (
                    'ok' => true,
                    'name' => $file->getName(),
                    'filename' => $file->getFilename(),
                    'size_readable' => $file->getSizeReadable(),
                    'upload_id' => $file->pk(),
                    'mime' => $file->mime
                );
                echo $this->getJson($data);
            } else {
               echo $this->getJson(array(
                    'ok' => false,
                    'error' => 'No files uploaded',
                ));
            }
        } catch (Am_Exception $e) {
            echo $this->getJson(array(
                    'ok' => false,
                    'error' => 'No files uploaded',
                ));
        }

    }

    public function uploadAction() {
        if (!$this->getDi()->uploadAcl->checkPermission($this->getParam('prefix'), 
                    Am_Upload_Acl::ACCESS_WRITE, 
                    $this->getDi()->authAdmin->getUser())) {
            throw new Am_Exception_AccessDenied();
        }

        $secure = $this->getParam('secure', false);

        $upload = new Am_Upload($this->getDi());
        $upload->setPrefix($this->getParam('prefix'));
        $upload->loadFromStored();
        $ids_before = $this->getUploadIds($upload);
        $upload->processSubmit('upload');
        //find currently uploaded file
        $x = array_diff($this->getUploadIds($upload), $ids_before);
        $upload_id = array_pop($x);
        try {
            $upload = $this->getDi()->uploadTable->load($upload_id);

            $data = array (
                'ok' => true,
                'name' => $upload->getName(),
                'size_readable' => $upload->getSizeReadable(),
                'upload_id' => $secure ?  Am_Form_Element_Upload::signValue($upload->pk()) : $upload->pk(),
                'mime' => $upload->mime
            );
            echo $this->getJson($data);

        } catch (Am_Exception $e) {
            echo $this->getJson(array(
                'ok' => false,
                'error' => 'No files uploaded',
            ));
        }
    }

    public function getSizeAction() {
        $file = $this->getDi()->uploadTable->load($this->getParam('id'));

        if (!$file) {
            throw new Am_Exception_InputError(
            'Can not fetch file for id : ' . $this->getParam('id')
            );
        }

        if (!$this->getDi()->uploadAcl->checkPermission($file, 
                    Am_Upload_Acl::ACCESS_READ, 
                    $this->getDi()->authAdmin->getUser())) {
            throw new Am_Exception_AccessDenied();
        }
        
        if ( $size = getimagesize($file->getFullPath()) ) {
            echo $this->getJson(
                array (
                    'width' => $size[0],
                    'height' => $size[1]
                )
            );
        } else {
            echo $this->getJson(false);
        }

    }
}

