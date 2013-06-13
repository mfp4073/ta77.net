<?php

class Am_Grid_Action_Group_Delete extends Am_Grid_Action_Group_Abstract
{
    protected $id = 'group-delete';
    protected $batchCount = 10;
    protected $isDelete = true;
    
    public function __construct($id = null, $title = null)
    {
        $this->title = ___("Delete");
        parent::__construct($id, $title);
    }
    
    public function handleRecord($id, $record)
    {
        $this->grid->getDataSource()->deleteRecord($id, $record);
    }
}