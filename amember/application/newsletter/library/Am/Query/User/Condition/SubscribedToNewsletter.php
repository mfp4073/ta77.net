<?php

class Am_Query_User_Condition_SubscribedToNewsletter extends Am_Query_Condition
    implements Am_Query_Renderable_Condition
{
    protected $list_ids;
    protected $title = "Subscribed to Newsletter Lists:";
    function __construct(array $list_ids = null) {
        $this->list_ids = $list_ids;
    }
    function getJoin(Am_Query $q){
        $ids = array_map('intval', $this->list_ids);
        if (!$ids) return null;
        $listCond = ' AND n.list_id IN (' . join(',',$ids) .') AND u.unsubscribed=0 ';
        return "INNER JOIN ?_newsletter_user_subscription n ON u.user_id=n.user_id {$listCond} AND n.is_active > 0";
    }
    //** for rendering */
    public function setFromRequest(array $input) {
        $id = $this->getId();
        $this->list_ids = null;
        if (array_key_exists($id, $input))
        {
            $this->list_ids = array();
            foreach ($input[$id]['val'] as $v)
                $this->list_ids[] = (int)$v;
            return true;
        }
    }
    public function getId(){ return '-newsletters'; }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options['Newsletter Lists'][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('val', array('multiple'=>'multiple', 'size'=>5))
           ->loadOptions(Am_Di::getInstance()->newsletterListTable->getAdminOptions());
    }
    public function isEmpty() {
        return empty($this->list_ids);
    }
    public function getDescription() {
        $ids = join(',', $this->list_ids);
        return "subscribed to newsletter lists #".$ids;
    }
    public function getLists(){
        return (array)$this->list_ids;
    }
}
