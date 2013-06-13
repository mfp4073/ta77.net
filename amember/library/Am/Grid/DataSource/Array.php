<?php

/**
 * Represents "array" as data source for grid
 * @package Am_Grid 
 */
class Am_Grid_DataSource_Array implements Am_Grid_DataSource_Interface_Editable
{
    protected $array = array();
    public function __construct(array $array)
    {
        foreach ($array as $a)
            $this->array[$this->getHash($a)] = $a;
    }
    public function getFoundRows()
    {
        return count($this->array);
    }
    public function selectPageRecords($page, $itemCountPerPage)
    {
        return array_map(create_function('$a', 'return (object)$a;'),
            array_slice($this->array, $page*$itemCountPerPage, $itemCountPerPage));
    }
    public function setOrder($fieldNameOrRaw, $desc=null) {
        switch (is_null($desc)) {
            case true :
                if ($fieldNameOrRaw) {
                    $this->_setOrderRaw($fieldNameOrRaw);
                }
                break;
            case false :
                $this->_setOrder($fieldNameOrRaw, $desc);
                break;
        }
    }
    protected function _setOrder($fieldName, $desc) {
        $records = $this->array;
        usort($records, create_function('$a,$b', '
            if ($a->' . $fieldName . '>$b->' . $fieldName . ') {
                return ' . ($desc ? 'true' : 'false') . ' ? -1 : 1;
            } elseif ($a->' . $fieldName . ' == $b->' . $fieldName . ') {
                return 0;
            } else {
                return ' . ($desc ? 'true' : 'false') . ' ? 1 : -1;
            }'));

        //unique indexes has been lost while sorting
        //we will restore it
        $result = array();
        foreach ($records as $record) {
            $result[$this->getHash($record)] = $record;
        }

        $this->array = $result;
    }
    protected function _setOrderRaw($raw) {
        //@todo Parse Raw Order and use _setOrder
    }

    //this method is only for use in Am_Grid_Filter
    public function _friendGetArray() {
        return $this->array;
    }

    //this method is only for use in Am_Grid_Filter
    public function _friendSetArray($records) {
        return $this->array = $records;
    }

    public function getDataSourceQuery()
    {
        throw new Am_Exception_NotImplemented(__METHOD__);
    }
    public function getIdForRecord($record)
    {
        return $this->getHash($record);
    }
    protected function getHash($record)
    {
        return md5(serialize(get_object_vars($record)));
    }
    public function createRecord()
    {
        return new stdclass;
    }
    public function deleteRecord($id, $record)
    {
        unset($this->array[$id]);
    }
    public function getRecord($id)
    {
        return (object)$this->array[$id];
    }
    public function insertRecord($record, $valuesFromForm)
    {
        throw new Am_Exception_NotImplemented(__METHOD__);
    }
    public function updateRecord($record, $valuesFromForm)
    {
        throw new Am_Exception_NotImplemented(__METHOD__);
    }
}