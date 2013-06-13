<?php

/**
 * Renderable users query
 * @package Am_Query
 */
class Am_Query_User extends Am_Query_Renderable 
{
    protected $template = 'admin/_user-search.phtml';
    function  __construct() {
        parent::__construct(Am_Di::getInstance()->userTable, 'u');
    }
    function initPossibleConditions(){
        if ($this->possibleConditions) return; // already initialized
        $t = new Am_View;
        $record = $this->table->createRecord();
        $baseFields = $record->getTable()->getFields();
        foreach ($baseFields as $field => $def){
            $title = ucwords(str_replace('_', ' ',$field));
            $f = new Am_Query_User_Condition_Field($field, $title, $def->type);
            $this->possibleConditions[] = $f;
        }
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionTo(null, null, 'any-completed', "Subscribed to any of (including expired):");
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveNoSubscriptionTo(null, 'none-completed', "Having no active subscription to:");
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionTo(null, User::STATUS_ACTIVE, 'active', "Having active subscription to:");
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionTo(null, User::STATUS_EXPIRED, 'expired', "Having expired subscription to:");
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionDue;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveCancellationDue;
        $this->possibleConditions[] = new Am_Query_User_Condition_HavePaymentBetween;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionDate;
        $this->possibleConditions[] = new Am_Query_User_Condition_Usergroup;
        $this->possibleConditions[] = new Am_Query_User_Condition_NoUsergroup;
        // add payment search options
        $this->possibleConditions[] = new Am_Query_User_Condition_Filter;
        $this->possibleConditions[] = new Am_Query_User_Condition_UserId;

        $event = Am_Di::getInstance()->hook->call(Am_Event::USER_SEARCH_CONDITIONS);
        $this->possibleConditions = array_merge($this->possibleConditions, $event->getReturn());
    }
}

/**
 * Filter users by field value
 */
class Am_Query_User_Condition_Field extends Am_Query_Renderable_Condition_Field
{
    protected $fieldGroupTitle = 'User Base Fields';
    
    public function renderElement(HTML_QuickForm2_Container $form) {
        $knownSelects = array(
            'status' => array(0=>'Pending',1=>'Active','2'=>'Expired'),
            'is_affiliate' => array(0=>'Not Affiliate',1=>'Affiliate', 2=>'Only Affiliate,not a member'),
            'is_approved'  => array(0=>'NO', 1=>'YES'),
            'unsubscribed'  => array(0=>'NO', 1=>'YES'),
            'is_locked'  => array(0=>'NO', 1=>'YES'),
            'i_agree'  => array(0=>'NO', 1=>'YES'),
            'email_verified'  => array(0=>'NO', 1=>'YES')
        );
        if (array_key_exists($this->field, $knownSelects)){
           $group = $this->addGroup($form);
           $group->addSelect('val')->loadOptions($knownSelects[$this->field]);
        } else
            return parent::renderElement($form);
    }
}

/**
 * Filter users having a subscription
 */
class Am_Query_User_Condition_HaveSubscriptionTo extends Am_Query_Condition
implements Am_Query_Renderable_Condition {
    protected $product_ids;
    protected $currentStatus = null;
    protected $alias = null;
    protected $title = null;
    protected $id;
    protected $empty = true;
    function __construct(array $product_ids=null, $currentStatus=null, $id=null, $title=null) {
        $this->product_ids = $product_ids ? $product_ids : array();
        if ($currentStatus !== null)
            $this->currentStatus = (int)$currentStatus;
        $this->id = $id;
        $this->title = $title;
    }
    function setAlias($alias = null)
    {
        $this->alias = $alias===null ? 'p'.substr(uniqid(), -4, 4) : $alias;
    }
    function getAlias(){
        if (!$this->alias)
            $this->setAlias();
        return $this->alias;
    }
    function getJoin(Am_Query $q){
        $alias = $this->getAlias();
        $ids = array_map('intval', $this->product_ids);
        $productsCond = $ids ? ' AND '.$alias.'.product_id IN (' . join(',',$ids) .')' : '';
        $statusCond = ($this->currentStatus !== null) ? " AND $alias.status=" . (int)$this->currentStatus : null;
        return "INNER JOIN ?_user_status $alias ON u.user_id=$alias.user_id{$productsCond}{$statusCond}";
    }
    /// for rendering
    public function setFromRequest(array $input) {
        $id = $this->getId();
        $this->product_ids = null;
        $this->empty = true;
        if (!empty($input[$id]['product_ids']))
        {
            $this->product_ids = array_map('intval', $input[$id]['product_ids']);
            $this->empty = false;
            return true;
        }
    }
    public function getId(){ return '-payments-'.$this->id; }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options['User Subscriptions Status'][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions(Am_Di::getInstance()->productTable->getOptions());
    }
    public function isEmpty() {
        return $this->empty;
    }
    public function getDescription(){
        if ($this->currentStatus!==null) {
            if ($this->currentStatus == User::STATUS_ACTIVE) $completedCond = 'active';
            elseif ($this->currentStatus == User::STATUS_EXPIRED) $completedCond = 'expired';
            else $completedCond = 'pending';
        } else
            $completedCond = "any";
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : 'any product';
        return htmlentities("have {$completedCond} subscription to $ids");
    }
}

class Am_Query_User_Condition_HaveNoSubscriptionTo extends Am_Query_Condition
implements Am_Query_Renderable_Condition {
    protected $product_ids;
    protected $currentStatus = null;
    protected $alias = null;
    protected $title = null;
    protected $id;
    protected $empty = true;
    function __construct(array $product_ids=null, $id=null, $title=null) 
    {
        $this->product_ids = $product_ids ? $product_ids : array();
        $this->id = $id;
        $this->title = $title;
    }
    public function getId(){ return '-no-payments-'.$this->id; }
    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = join(',', array_filter(array_map('intval', $this->product_ids)));
        if (!$ids) return null;
        return "NOT EXISTS 
            (SELECT * FROM ?_user_status ncmss 
            WHERE ncmss.user_id=$a.user_id AND ncmss.product_id IN ($ids) AND ncmss.status = 1)";
    }
    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : 'any product';
        return htmlentities("have no active subscriptions to $ids");
    }
    public function renderElement(HTML_QuickForm2_Container $form) 
    {
       $form->options['User Subscriptions Status'][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions(Am_Di::getInstance()->productTable->getOptions());
    }
    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = null;
        $this->empty = true;
        if (!empty($input[$id]['product_ids']))
        {
            $this->product_ids = array_map('intval', $input[$id]['product_ids']);
            $this->empty = false;
            return true;
        } 
    }
    public function isEmpty()
    {
        return $this->empty;
    }
}

class Am_Query_User_Condition_HaveSubscriptionDue extends Am_Query_Condition
implements Am_Query_Renderable_Condition {
    protected $product_ids = array();
    protected $date_start;
    protected $date_end;
    protected $empty = true;

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = $this->date_start = $this->date_end = null;
        $this->empty = true;

        $this->product_ids = isset($input[$id]['product_ids']) ? $input[$id]['product_ids'] : array();
        $this->date_start = @$input[$id]['date_start'];
        $this->date_end = @$input[$id]['date_end'];

        if ($this->date_start && $this->date_end)
            $this->empty = false;

        return !$this->empty;
    }
    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = 'Has subscription that expire between dates:';

       $form->options['User Subscriptions Status'][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions(Am_Di::getInstance()->productTable->getOptions());
       $group->addDate('date_start');
       $group->addDate('date_end');
    }
    public function isEmpty()
    {
        return $this->empty;
    }

    public function getId(){ return 'subscription-due'; }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : 'any product';
        return htmlentities(sprintf("have subscription for %s
            that expire between %s and %s", $ids,
            amDate($this->date_start), amDate($this->date_end)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hsdac.id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        $date_end = $db->escape($this->date_end);
        if (!$date_start || !$date_end) return null;
        return "EXISTS (SELECT * FROM ?_access_cache hsdac
            WHERE hsdac.user_id=$a.user_id AND $product_cond AND hsdac.fn = 'product_id' AND hsdac.expire_date BETWEEN $date_start AND $date_end)";
    }
}
class Am_Query_User_Condition_HaveSubscriptionDate extends Am_Query_User_Condition_HaveSubscriptionDue
implements Am_Query_Renderable_Condition {
    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = $this->date_start = $this->date_end = null;
        $this->empty = true;

        $this->product_ids = isset($input[$id]['product_ids']) ? $input[$id]['product_ids'] : array();
        $this->date_start = @$input[$id]['date_start'];

        if ($this->date_start)
            $this->empty = false;

        return !$this->empty;
    }
    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = 'Has subscription on date:';

       $form->options['User Subscriptions Status'][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions(Am_Di::getInstance()->productTable->getOptions());
       $group->addDate('date_start');
    }

    public function getId(){ return 'subscription-date'; }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : 'any product';
        return htmlentities(sprintf("have subscription for %s
            for date %s ", $ids,
            amDate($this->date_start)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hsdt.product_id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        if (!$date_start) return null;
        return "EXISTS (SELECT * FROM ?_access hsdt
            WHERE hsdt.user_id=$a.user_id AND $product_cond AND $date_start BETWEEN hsdt.begin_date AND hsdt.expire_date )";
    }
}

class Am_Query_User_Condition_HavePaymentBetween extends Am_Query_User_Condition_HaveSubscriptionDue
implements Am_Query_Renderable_Condition {
    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = 'Has payment made between dates:';

       $form->options['User Subscriptions Status'][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions(Am_Di::getInstance()->productTable->getOptions());
       $group->addDate('date_start');
       $group->addDate('date_end');
    }

    public function getId(){ return 'payment-between'; }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : 'any product';
        return htmlentities(sprintf("have payment for %s
            that made between %s and %s", $ids,
            amDate($this->date_start), amDate($this->date_end)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hspbetit.item_id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        $date_end = $db->escape($this->date_end);
        if (!$date_start || !$date_end) return null;
        return "EXISTS (SELECT hspbet.* FROM ?_invoice_payment hspbet , ?_invoice hspbeti, ?_invoice_item hspbetit
            WHERE hspbet.user_id=$a.user_id 
                AND hspbet.invoice_id = hspbeti.invoice_id 
                AND hspbetit.invoice_id=hspbet.invoice_id 
                AND $product_cond AND  hspbet.dattm BETWEEN $date_start AND $date_end)";
    }
}
class Am_Query_User_Condition_HaveCancellationDue extends Am_Query_User_Condition_HaveSubscriptionDue
implements Am_Query_Renderable_Condition {
    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = 'Has invoice canceled between dates:';

       $form->options['User Subscriptions Status'][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions(Am_Di::getInstance()->productTable->getOptions());
       $group->addDate('date_start');
       $group->addDate('date_end');
    }

    public function getId(){ return 'canceled-between'; }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : 'any product';
        return htmlentities(sprintf("have invoice for %s
            that canceled between %s and %s", $ids,
            amDate($this->date_start), amDate($this->date_end)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hspbetit.item_id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        $date_end = $db->escape($this->date_end);
        if (!$date_start || !$date_end) return null;
        return "EXISTS (SELECT hspbet.* FROM ?_invoice_payment hspbet , ?_invoice hspbeti, ?_invoice_item hspbetit
            WHERE hspbet.user_id=$a.user_id 
                AND hspbet.invoice_id = hspbeti.invoice_id 
                AND hspbetit.invoice_id=hspbet.invoice_id 
                AND $product_cond AND  hspbeti.tm_cancelled BETWEEN $date_start AND $date_end)";
    }
}

class Am_Query_User_Condition_Filter
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title = "Quick Filter";
    protected $filter;
    public function getId() {
        return 'filter';
    }
    public function isEmpty() {
        return $this->filter === null;
    }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options['Quick Filter'][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addText('val');
    }
    public function setFromRequest(array $input) {
        if (is_string($input)) {
            $this->filter = $input;
            return true;
        } elseif (@$input['filter']['val']!='') {
            $this->filter = $input['filter']['val'];
            return true;
        }
    }
    public function _getWhere(Am_Query $q){
        $a = $q->getAlias();
        $f = '%'.$this->filter.'%';
        return $q->escapeWithPlaceholders("($a.login LIKE ?) OR ($a.email LIKE ?) OR ($a.name_f LIKE ?) OR ($a.name_l LIKE ?)
            OR ($a.remote_addr LIKE ?)
            OR ($a.user_id IN (SELECT user_id FROM ?_invoice WHERE public_id=? OR CAST(invoice_id as char(11))=?))
            OR ($a.user_id IN (SELECT user_id FROM ?_invoice_payment WHERE receipt_id=?))
            ",
                $f, $f, $f, $f, $f, $this->filter, $this->filter, $this->filter);
    ;}
    public function getDescription(){
        $f = htmlentities($this->filter);
        return "username, e-mail or name contains string [$f]";
    }
}

class Am_Query_User_Condition_UserId
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title = "UserId#";
    protected $ids = null;
    public function getId() {
        return 'member_id_filter';
    }
    public function isEmpty() {
        return !empty($this->ids);
    }
    public function renderElement(HTML_QuickForm2_Container $form) {
       //$form->options['Quick Filter'][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addText('val');
    }
    public function setIds($ids){
        if (!is_array($ids)) $ids = split(',', $ids);
        $this->ids = array_filter(array_map('intval', $ids));
    }
    public function setFromRequest(array $input) {
        if (@$input[$this->getId()]['val']!='') {
            $this->setIds($input[$this->getId()]['val']);
            return true;
        }
    }
    public function _getWhere(Am_Query $q){
        if (!$this->ids) return null;
        $a = $q->getAlias();
        $ids = join(',', $this->ids);
        return "$a.user_id IN ($ids)";
    ;}
    public function getDescription(){
        $ids = join(',', $this->ids);
        return "user_id IN ($ids)";
    }
}

class Am_Query_User_Condition_Usergroup
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $ids = array();
    
    public function __construct()
    {
        $this->title = ___("Assigned to usergroup");
    }
    public function getId() {
        return 'user-group';
    }
    public function isEmpty() {
        return !$this->ids;
    }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options['User Groups'][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addSelect('ids', 'multiple=multiple size=5')->loadOptions($this->getOptions());
    }
    protected function getOptions()
    {
        return Am_Di::getInstance()->userGroupTable->getSelectOptions();
    }
    public function setFromRequest(array $input) 
    {
        $this->ids = @$input[$this->getId()]['ids'];
        $this->ids = array_filter(array_map('intval', (array)$this->ids));
        if ($this->ids)
            return true;
    }
    public function getJoin(Am_Query $q)
    {
        $a = $q->getAlias();
        $ids = array_filter(array_map('intval', $this->ids));
        $ids = $ids ? implode(',', $ids) : '-1';
        return "INNER JOIN ?_user_user_group uug ON $a.user_id = uug.user_id AND uug.user_group_id IN ($ids)";
    }
    public function getDescription(){
        $g = $this->getOptions();
        $g = array_intersect_key($g, array_combine($this->ids, $this->ids));
        $g = array_map(array('Am_Controller', 'escape'), $g);
        $g = implode(',', $g);
        return "assigned to usergroups [$g]";
    }
}

class Am_Query_User_Condition_NoUsergroup 
    extends Am_Query_User_Condition_Usergroup
{
    public function __construct()
    {
        $this->title = ___("Not assigned to usergroup");
    }
    public function getId() {
        return 'no-user-group';
    }    
    public function getJoin(Am_Query $q)
    {
        return;
    }
    public function _getWhere(Am_Query $q)
    {
        $a = $q->getAlias();
        $ids = array_filter(array_map('intval', $this->ids));
        if (!$ids) return;
        $ids = implode(',', $ids);
        return "NOT EXISTS (SELECT * FROM ?_user_user_group uug WHERE $a.user_id = uug.user_id AND uug.user_group_id IN ($ids))";
    }
    
}