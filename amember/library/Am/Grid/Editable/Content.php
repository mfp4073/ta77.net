<?php
/*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.amember.com/
*    Release: 4.2.17
*    License: LGPL http://www.gnu.org/copyleft/lesser.html
*/

abstract class Am_Grid_Editable_Content extends Am_Grid_Editable
{
    public function __construct(Am_Request $request, Am_View $view)
    {
        //Am_Db::setLogger();
        $adapter = $this->joinSort($this->createAdapter());
        parent::__construct('_'.$this->getContentGridId(), $this->getTitle(), $adapter, $request, $view);
        
        $this->addCallback(self::CB_AFTER_INSERT, array($this, 'afterInsert'));
        $this->addCallback(self::CB_AFTER_UPDATE, array($this, 'afterInsert'));
        
        $this->addCallback(self::CB_VALUES_TO_FORM, array($this, '_valuesToForm'));
        foreach ($this->getActions() as $action)
            $action->setTarget('_top');
    }
    
    /** 
     * Add join to resource_access_sort and sort field
     * to be present in the result
     */
    protected function joinSort(Am_Query $q)
    {
        $q->getTable()->addDefaultSort($q);
        return $q;
    }
    
    public function getTitle()
    {
        return ___(ucfirst($this->getContentGridId()));
    }
    public function getContentGridId()
    {
        $id = explode('_', get_class($this));
        $id = strtolower(array_pop($id));
        return $id;
    }
    
    function renderAccessTitle(ResourceAbstract $r)
    {
        $title = Am_Controller::escape($r->title);
        if (!empty($r->hide))
            $title = "<span class='disabled-text'>$title</span>";
        return $this->renderTd($title, false);
    }
    
    public function getPermissionId()
    {
        return 'grid_content';
    }
    
    protected function initGridFields()
    {
        $this->addGridField('_access', ___('Products'), false)->setRenderFunction(array($this, 'renderProducts'));
        $this->addGridField('_link', ___('Link'), false)->setRenderFunction(array($this, 'renderLink'));
        $this->actionAdd(new Am_Grid_Action_SortContent());
        parent::initGridFields();
    }

    public function renderLink(ResourceAbstract $resource)
    {
        $html = "";
        $url = $resource->getUrl();
        if (!empty($url)) 
            $html = sprintf('<a href="%s" target="_blank">%s</a>', 
                Am_Controller::escape($url), ___('link'));
        return $this->renderTd($html, false);
    }
    public function renderProducts(ResourceAbstract $resource)
    {
        $access_list = $resource->getAccessList();
        if (count($access_list) > 6)
            $s = ___('%d access records...', count($access_list));
        else
        {
            $s = "";
            foreach ($access_list as $access)
            {
                $l = "";
                if ($access->getStart())
                    $l .= " from " . $access->getStart();
                if ($access->getStop())
                    $l .= " to " . $access->getStop();
                $s .= sprintf("%s <b>%s</b> %s<br />\n", $access->getClassTitle(), $access->getTitle(), $l);
            }
        }
        return $this->renderTd($s, false);
    }

    public function afterInsert(array & $values, ResourceAbstract $record)
    {
        $record->clearAccess();
        
        foreach(array(
                'free' => ResourceAccess::FN_FREE, 
                'free_without_login' => ResourceAccess::FN_FREE_WITHOUT_LOGIN, 
                'product_id' => ResourceAccess::FN_PRODUCT, 
                'product_category_id' => ResourceAccess::FN_CATEGORY) 
            as $key => $rtype)
        {
            if(!empty($values['_access'][$key]))
            {
                foreach ($values['_access'][$key] as $id => $params)
                {
                    if (!is_array($params))
                        $params = json_decode($params, true);
                    $record->addAccessListItem($id, $params['start'], $params['stop'], $rtype);
                }
            }
        }
        
    }

    public function _valuesToForm(array & $values)
    {
        $values['_access'] = $this->getRecord()->getAccessList();
    }

    public function renderPath(ResourceAbstractFile $file)
    {

        $upload = $file->getUpload();

        try{
            $file->isLocal();
        } catch (Exception $e) {
            if (!$upload)
                return $this->renderTd(
                    '<span class="error">' . ___('The file has been removed from disk or corrupted. Please re-upload it.') . '</span>' .
                    '<br />' . ___('Real Path') . ': ' . Am_Controller::escape($file->path) .
                    '<br /><span style="color: grey">' . ___('Error from Storage Engine') . ': ' . Am_Controller::escape($e->getMessage()) . '</span>', false );
        }

        return $upload && !file_exists($upload->getFullPath()) ?
            $this->renderTd(
                '<div class="reupload-conteiner-hide"><span class="error">' . ___('The file has been removed from disk or corrupted. Please re-upload it.') . '</span>'.
                '<div class="reupload-conteiner"><span class="upload-name">' . $this->escape($upload->getName() . '/' . $upload->getFilename()) . '</span><br />' .
                '<div><span class="reupload" data-upload_id="' . $upload->pk() . '"  data-return-url="' . $this->escape($this->makeUrl()) . '" id="reupload-' . $upload->pk() . '"></span></div></div></div>', false) :
            $this->renderTd(sprintf('%s <span style="color:grey">(%s)</span>',
                    $this->escape($file->getDisplayFilename()), $file->getStorageId()), false);
    }
}

