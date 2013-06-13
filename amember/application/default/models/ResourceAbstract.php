<?php

/** Class represents a resource record with */
abstract class ResourceAbstract extends Am_Record
{
    
    public function getAccessType()
    {
        return $this->getTable()->getAccessType();
    }
    public function getAccessTitle()
    {
        return $this->getTable()->getAccessTitle();
    }
   
    public function delete()
    {
        parent::delete();
        $this->clearAccess();
        $this->getAdapter()->query("DELETE FROM ?_resource_access_sort
            WHERE resource_id=?d AND resource_type=?", 
                $this->pk(), $this->getAccessType());
    }
    public function clearAccess()
    {
        if (!$this->pk()) return;
        return $this->getDi()->resourceAccessTable->deleteBy(
            array('resource_type' => $this->getAccessType(), 
                  'resource_id' => $this->pk())
        );
    }
    public function getAccessList()
    {
        if (!$this->pk()) return array();
        return $this->getDi()->resourceAccessTable->findBy(
            array('resource_type' => $this->getAccessType(), 
                  'resource_id' => $this->pk())
        );
    }
    /**
     * Add a resource access record
     * @param int $itemId product# or category# or -1
     * @param string $startString 1d or 3m or 0d - for zero autoresponder
     * @param string $stopString
     * @param bool $isProduct is a product or category
     * @return ResourceAccess 
     */
    public function addAccessListItem($itemId, $startString, $stopString, $fn)
    {
        if (!$this->pk())
            throw new Am_Exception_InternalError("empty PK - could not execute " . __METHOD__);
        
        $fa = $this->getDi()->resourceAccessRecord;
        $fa->resource_type = $this->getAccessType();
        $fa->resource_id = $this->pk();
        
        $fa->fn = $fn;
        $fa->id = $itemId;
        $fa->start_days = null;
        $fa->stop_days = null;
        if (preg_match('/^(-?\d+)(\w+)$/', strtolower($startString), $regs))
        {
            $fa->start_days = $regs[1];
        }
        if (preg_match('/^(-?\d+)(\w+)$/', strtolower($stopString), $regs))
        {
            $fa->stop_days = $regs[1];
        }
        $fa->insert();
        return $fa;
    }
    /** Has the folder items in access list with not-default start/stop */
    public function hasCustomStartStop()
    {
        foreach ($this->getAccessList() as $access)
            if ($access->hasCustomStartStop()) return true;
    }
    public function hasAnyProducts()
    {
        foreach ($this->getAccessList() as $access)
            if ($access->isAnyProducts()) return true;
    }
    public function hasCategories()
    {
        foreach ($this->getAccessList() as $access)
            if ($access->getClass() != 'product') return true;
    }
    
    public function getUrl()
    {
        return null;
    }
    public function getLinkTitle()
    {
        return $this->title;
    }
    public function renderLink()
    {
        if (!empty($this->hide))
            return;
        $url = $this->getUrl();
        $title = $this->getLinkTitle();
        if (empty($title)) 
            return;
        if ($url)
        {
            return sprintf('<a href="%s">%s</a>', Am_Controller::escape($url), $title);
        } else {
            return $title;
        }
    }

    function hasAccess(User $user = null)
    {
        if ($user === null)
            return $this->getDi()->resourceAccessTable->guestHasAccess($this->pk(), $this->getAccessType());
        else
            return $this->getDi()->resourceAccessTable->userHasAccess($user, $this->pk(), $this->getAccessType());
    }
    
    /**
     * 
     * @return array of int or ResourceAccess::ANY_PRODUCT
     */
    function findMatchingProductIds()
    {
        $ret = $this->getTable()->getAdapter()->selectCol("
            SELECT DISTINCT ?
            FROM 
                ?_resource_access ra 
            WHERE ra.fn = 'product_category_id' AND ra.resource_id=?d AND ra.id = ?
            
            UNION 

            SELECT DISTINCT `id`
            FROM ?_resource_access ra
            WHERE ra.fn = 'product_id' AND ra.resource_id=?d
            
            UNION 
            
            SELECT DISTINCT ppc.product_id 
            FROM 
                ?_product_product_category ppc 
                LEFT JOIN 
                ?_resource_access ra ON ppc.product_category_id = ra.id
            WHERE ra.fn = 'product_category_id' AND ra.resource_id=?d
        ", 
            ResourceAccess::ANY_PRODUCT, $this->pk(), ResourceAccess::ANY_PRODUCT, 
            $this->pk(), 
            $this->pk());
        
        if ($ret && ($ret[0] == ResourceAccess::ANY_PRODUCT)) return ResourceAccess::ANY_PRODUCT;
        return $ret;
    }
    
    public function insert($reload = true)
    {
        $ret = parent::insert(true);
        $max = $this->getAdapter()->selectCell(
                "SELECT MAX(sort_order) FROM ?_resource_access_sort");
        $this->getAdapter()->query("
            INSERT INTO ?_resource_access_sort
            SET resource_id=?d, resource_type=?,
                sort_order=?d
        ", $this->pk(), $this->getAccessType(), 1+$max);
        return $ret;
    }
    
    function setSortOrder($sort)
    {
        $this->getAdapter()->query(
            "INSERT INTO ?_resource_access_sort 
             SET resource_id=?, resource_type=?, sort_order=?d
             ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order)",
            $this->pk(), $this->getAccessType(), $sort
        );
        return $this;
    }
    
    function getSortOrder()
    {
        return $this->getAdapter()->selectCell(
            "SELECT sort_order FROM ?_resource_access_sort
             WHERE resource_id=? AND resource_type=?",
            $this->pk(), $this->getAccessType()
        );
    }
    
    function setSortBetween($after, $before, $afterType = null, $beforeType = null)
    {
        if ($afterType === null) $afterType = $this->getAccessType ();
        if ($beforeType === null) $beforeType = $this->getAccessType ();

        $db = $this->getAdapter();
        if ($before)
        {
            $beforeSort = (int)$db->selectCell("SELECT sort_order
                FROM ?_resource_access_sort
                WHERE resource_id=? AND resource_type=?
            ", $before, $beforeType);
            if (!$beforeSort) return ; // something is wrong
            if (!$prevRow = $db->selectRow("SELECT resource_id, resource_type
                FROM ?_resource_access_sort
                WHERE sort_order=?", $beforeSort-1))
            {
                $this->setSortOrder($beforeSort-1);
            } else { // $prevRow is exists 
                if (($prevRow['resource_id'] == $this->pk()) && 
                    ($prevRow['resource_type'] == $this->getAccessType()))
                    return; // we already have it set correctly
                // prevRow is busy, lets do shift
                $db->query("UPDATE ?_resource_access_sort
                    SET sort_order=sort_order+1
                    WHERE sort_order >= ?d", $beforeSort);
                $this->setSortOrder($beforeSort);
            }
        } elseif ($after) {
            $afterSort = (int)$db->selectCell("SELECT sort_order
                FROM ?_resource_access_sort
                WHERE resource_id=? AND resource_type=?
            ", $after, $afterType);
            if (!$afterSort) return ; // something is wrong
            if (!$prevRow = $db->selectRow("SELECT resource_id, resource_type
                FROM ?_resource_access_sort
                WHERE sort_order=?", $afterSort+1))
            {
                $this->setSortOrder($afterSort+1);
            } else { // $prevRow is exists 
                if (($prevRow['resource_id'] == $this->pk()) && 
                    ($prevRow['resource_type'] == $this->getAccessType()))
                    return; // we already have it set correctly
                // prevRow is busy, lets do shift
                $db->query("UPDATE ?_resource_access_sort
                    SET sort_order=sort_order+1
                    WHERE sort_order >= ?d", $afterSort+1);
                $this->setSortOrder($afterSort+1);
            }
        }
    }
}

abstract class ResourceAbstractTable extends Am_Table
{
    /** @return string for exampe: 'folder', 'file', 'page' */
    abstract function getAccessType();
    /** @return string translated title of access type, for example 'Folder' */
    abstract function getAccessTitle(); 
    function getTitleField()
    {
        return 'title';
    }
    abstract function getPageId();
    
    function addDefaultSort(Am_Query $q)
    {
        $a = $q->getAlias();
        $type = $this->createRecord()->getAccessType();
        $q->leftJoin('?_resource_access_sort', "ras", "$a.{$this->_key} = ras.resource_id AND ras.resource_type='$type'");
        $q->addField('ras.sort_order', '_sort_order');
        $q->setOrder('_sort_order');
    }
}