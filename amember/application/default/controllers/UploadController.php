<?php

class_exists('Am_Form', true);

class UploadController extends Am_Controller
{

    public function getAction()
    {

        if ($path = $this->getParam('path')) {
            $upload = $this->getDi()->uploadTable->findFirstByPath($path);
        } else {
            $upload = $this->getDi()->uploadTable->load($this->getParam('id'));
        }

        if (!$upload)
        {
            throw new Am_Exception_InputError(
                'Can not fetch file for id: ' . $this->getParam('id')
            );
        }

        if (!$this->getDi()->uploadAcl->checkPermission($upload,
                Am_Upload_Acl::ACCESS_READ,
                $this->getDi()->auth->getUser()))
        {
            throw new Am_Exception_AccessDenied();
        }


        $this->_helper->sendFile($upload->getFullPath(), $upload->getType(),
            array(
                //'cache'=>array('max-age'=>3600),
                'filename' => $upload->getName(),
        ));
        exit;
    }

    protected function getUploadIds(Am_Upload $upload)
    {
        $upload_ids = array();
        foreach ($upload->getUploads() as $upload)
        {
            $upload_ids[] = $upload->pk();
        }
        return $upload_ids;
    }

    public function uploadAction()
    {
        if (!$this->getDi()->uploadAcl->checkPermission($this->getParam('prefix'),
                Am_Upload_Acl::ACCESS_WRITE,
                $this->getDi()->auth->getUser()))
        {
            throw new Am_Exception_AccessDenied();
        }

        $secure = $this->getParam('secure', false);

        $upload = new Am_Upload($this->getDi());
        $upload->setPrefix($this->getParam('prefix'));
        $upload->loadFromStored();
        $ids_before = $this->getUploadIds($upload);
        $upload->processSubmit('upload', false);
        //find currently uploaded file
        $x = array_diff($this->getUploadIds($upload), $ids_before);
        $upload_id = array_pop($x);
        try
        {
            $upload = $this->getDi()->uploadTable->load($upload_id);

            $data = array(
                'ok' => true,
                'name' => $upload->getName(),
                'size_readable' => $upload->getSizeReadable(),
                'upload_id' => $secure ?  Am_Form_Element_Upload::signValue($upload->pk()) : $upload->pk(),
                'mime' => $upload->mime
            );
            echo $this->getJson($data);
        }
        catch (Am_Exception $e)
        {
            echo $this->getJson(array(
                'ok' => false,
                'error' => 'No files uploaded',
            ));
        }
    }

}

