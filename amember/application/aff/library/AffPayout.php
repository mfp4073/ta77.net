<?php
/**
 * Class represents records from table aff_payout
 * {autogenerated}
 * @property int $payout_id 
 * @property date $date 
 * @property date $thresehold_date 
 * @property double $total 
 * @property string $type 
 * @see Am_Table
 */
class AffPayout extends Am_Record 
{
    /**
     * @return AffPayoutDetail
     */
    function addDetail($aff_id, $amount)
    {
        $detail = $this->getDi()->affPayoutDetailRecord;
        $detail->aff_id = $aff_id;
        $detail->amount = $amount;
        $detail->payout_id = $this->pk();
        $detail->insert();
        $this->total += $amount;
        return $detail;
    }
    
    /** @return Am_Aff_PayoutMethod|null */
    function getPayoutMethod()
    {
        foreach (Am_Aff_PayoutMethod::getEnabled() as $k => $p)
            if ($k == $this->type)
                return $p;
    }
}

class AffPayoutTable extends Am_Table 
{
    protected $_key = 'payout_id';
    protected $_table = '?_aff_payout';
}
