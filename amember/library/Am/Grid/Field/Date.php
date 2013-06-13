<?php

class Am_Grid_Field_Date extends Am_Grid_Field
{
    const DATETIME = 'dt';
    const DATE = 'd';
    const TIME = 't';
   
    protected $format = self::DATETIME;
    public function __construct($field, $title, $sortable = true, $align = null, $renderFunc = null, $width = null)
    {
        parent::__construct($field, $title, $sortable, $align, $renderFunc, $width);
        $this->setFormatFunction(array($this, '_format'));
    }
    public function setFormatTime(){ $this->format = self::TIME; return $this;}
    public function setFormatDatetime(){ $this->format = self::DATETIME; return $this;}
    public function setFormatDate(){ $this->format = self::DATE; return $this;}
    public function _format($d)
    {
        if (trim($d)=='') return '';
        switch ($this->format)
        {
            case self::DATE:
                return amDate($d);
            case self::TIME:
                return amTime($d);
            default:
                return amDatetime($d);
        }
    }
}