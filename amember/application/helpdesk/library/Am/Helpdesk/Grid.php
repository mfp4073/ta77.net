<?php

class Am_Grid_Editable_Helpdesk extends Am_Grid_Editable {
    protected $foundRowsBeforeFilter = 0;
    
    function init() 
    {
        $this->foundRowsBeforeFilter = $this->dataSource->getFoundRows();
        $this->addCallback(Am_Grid_ReadOnly::CB_RENDER_TABLE, array($this, 'skipTable'));
    }
    
    function renderFilter() {
        if ($this->foundRowsBeforeFilter) {
            return parent::renderFilter ();
        }
    }
    
    function skipTable(& $out) {
        if (!$this->foundRowsBeforeFilter) {
            $out = '';
        }
    }
    public function getPermissionId()
    {
        return 'helpdesk';
    }
}

class Am_Grid_Filter_Helpdesk extends Am_Grid_Filter_Abstract {

    protected $language = null;

    protected $varList = array(
            'filter_q', 'filter_s', 'filter_c'
    );
    
    protected function applyFilter() {
       $query = $this->grid->getDataSource()->getDataSourceQuery();
       if ($filter = $this->getParam('filter_q')){
            $condition = new Am_Query_Condition_Field('subject', 'LIKE', '%'.$filter.'%');
            $condition->_or(new Am_Query_Condition_Field('ticket_mask', 'LIKE', '%'.$filter.'%'));

            $query->add($condition);
        }
        if ($filter = $this->getParam('filter_s')){
            $query->addWhere('t.status IN (?a)', $filter);
        }
        if ($filter = $this->getParam('filter_c')){
            $query->addWhere('t.category_id=?', $filter);
        }
    }

    function renderInputs() {

        $statusOptions = HelpdeskTicket::getStatusOptions();
        $categoryOptions = Am_Di::getInstance()->helpdeskCategoryTable->getOptions();

        $filter = ' ';
        $filter .= ___('Filter by String') . ' ';
        $filter .= $this->renderInputText('filter_q');

        if ($categoryOptions) {
            $categoryOptions = array(''=>___('All')) + $categoryOptions;
            $filter .= ' ';
            $filter .= $this->renderInputSelect('filter_c', $categoryOptions);
        }

        $filter .= '<br />';
        $filter .= $this->renderInputCheckboxes('filter_s', $statusOptions);

        return $filter;
    }
    
    function getTitle() {
        return '';
    }

    protected function filter($array, $filter) {
        if (!$filter) return $array;
        foreach ($array as $k=>$v) {
            if (false===strpos($k, $filter) &&
                    false===strpos($v, $filter)) {

                unset($array[$k]);
            }
        }
        return $array;
    }
}

class Am_Grid_Action_Ticket extends Am_Grid_Action_Abstract {
    protected $title = "New";
    protected $type = self::NORECORD; // this action does not operate on existing records
    protected $strategy = null;
    
    public function __construct($id = null, $title = null)
    {
        $this->title = ___("New");
        parent::__construct($id, $title);
    }
    
    public function run() {
        $form = $this->grid->getForm();

        if ($form->isSubmitted() && $form->validate()) {

            $values = $form->getValue();

            if (defined('AM_ADMIN') 
                    && isset($values['from'])
                    && $values['from'] == 'user') {
      
                $user = Am_Di::getInstance()->userTable->findFirstByLogin($values['loginOrEmail']);
                if (!$user) 
                    $user = Am_Di::getInstance()->userTable->findFirstByEmail($values['loginOrEmail']);
                if (!$user)
                    throw new Am_Exception_InputError("User not found with username or email equal to {$values['loginOrEmail']}");
                $this->switchStrategy(new Am_Helpdesk_Strategy_Member($user->pk()));
            }

            $ticket = Am_Di::getInstance()->helpdeskTicketRecord;
            $ticket->subject = $values['subject'];
            $ticket->created = Am_Di::getInstance()->sqlDateTime;
            $ticket->updated = Am_Di::getInstance()->sqlDateTime;
            $ticket->category_id = isset($values['category_id']) ? $values['category_id'] : null;
            if (($category = $ticket->getCategory()) && $category->owner_id) {
                $ticket->owner_id = $category->owner_id;
            }
            $ticket = $this->getStrategy()->fillUpTicketIdentity($ticket, $this->grid->getCompleteRequest());
            // mask will be generated on insertion
            $ticket->insert();
            $this->getStrategy()->onAfterInsertTicket($ticket);

            $message = Am_Di::getInstance()->helpdeskMessageRecord;
            $message->content = $values['content'];
            $message->ticket_id = $ticket->pk();
            $message->dattm = Am_Di::getInstance()->sqlDateTime;
            $message = $this->getStrategy()->fillUpMessageIdentity($message);
            $message->setAttachments(@$values['attachments']);
            $message->insert();
            $this->getStrategy()->onAfterInsertMessage($message);

            $this->restoreStrategy();

            echo $this->renderTicketSubmited($ticket);
        } else {
            echo $this->renderTitle();
            echo $form;
        }
    }
    
    /** @return Am_Helpdesk_Strategy_Abstract */
    protected function getStrategy()
    {
        return is_null($this->strategy) ?
                Am_Di::getInstance()->helpdeskStrategy :
                $this->strategy;
    }
    
    public function switchStrategy(Am_Helpdesk_Strategy_Abstract $strategy) {
        $this->strategy = $strategy;
    }

    public function restoreStrategy() {
        if (!is_null($this->strategy)) {
            $this->strategy = null;
        }
    }

    private function renderTicketSubmited($ticket)
    {
        $out = sprintf('<h1>%s</h1>', ___('Ticket has been submited'));
        $out .= sprintf('<p>%s <a href="%s" target="_top"><strong>#%s</strong></a></p>',
            ___('Reference number is:'),
            $this->getStrategy()->assembleUrl(array(
                'action' => 'view',
                'page_id' => 'view',
                'ticket' => $ticket->ticket_mask
            ), 'inside-pages'),
            $ticket->ticket_mask
        );
        return $out;
    }
}

abstract class Am_Helpdesk_Grid extends Am_Grid_Editable_Helpdesk
{
     public function __construct(Am_Request $request, Am_View $view) {
        $id = explode('_', get_class($this));
        $id = strtolower(array_pop($id));

        parent::__construct('_'.$id, $this->getGridTitle(), $this->createDs(), $request, $view);

        $this->setFilter(new Am_Grid_Filter_Helpdesk());
        $this->setRecordTitle(___('Ticket'));
    }

    abstract function getStatusIconId($id, $record);

    public function cbGetTrAttribs(& $ret, $record)
    {
        if ($record->status == HelpdeskTicket::STATUS_CLOSED)
        {
            $ret['class'] = isset($ret['class']) ? $ret['class'] . ' disabled' : 'disabled';
        }
    }

    public function initActions()
    {
        $this->actionAdd(new Am_Grid_Action_Ticket());
    }

    public function createForm() {
        return Am_Di::getInstance()->helpdeskStrategy->createNewTicketForm();
    }
    
    public function renderSubject($record)
    {
        $url = Am_Di::getInstance()->helpdeskStrategy->assembleUrl(array(
            'page_id' => 'view',
            'action' => 'view',
            'ticket' => $record->ticket_mask
        ), 'inside-pages');

        $category = $record->category_id ?
            sprintf(' (%s)', Am_Controller::escape($record->c_title)):
            '';

        return sprintf('<td><strong><a href="%s" target="_top">%s</a></strong>%s</td>',
            $url,
            Am_Controller::escape($record->subject),
            $category
        );
    }

    public function renderStatus($record)
    {
        $statusOptions = HelpdeskTicket::getStatusOptions();
        list($status) = explode('_', $record->status);
        $status = $this->getStatusIconId($status, $record);
        return sprintf('<td align="center">%s</td>',
            $this->getDi()->view->icon($status, $statusOptions[$record->status])
        );
    }

    public function renderTime($record, $fieldName)
    {
        return sprintf('<td>%s</td>', $this->getView()->getElapsedTime($record->$fieldName));
    }

    public function renderUser($record, $fieldName)
    {
        return sprintf('<td><a href="%s" target="_top">%s (%s %s)</a></td>',
            $this->getView()->userUrl($record->user_id),    
            $record->m_login,
            $record->m_name_f,
            $record->m_name_l
        );
    }

    protected function createDS() {
        $query = new Am_Query(Am_Di::getInstance()->helpdeskTicketTable);
        $query->addField('COUNT(msg.message_id) AS msg_cnt')
            ->addField('m.login AS m_login')
            ->addField('m.name_f AS m_name_f')
            ->addField('m.name_l AS m_name_l')
            ->leftJoin('?_helpdesk_message', 'msg', 'msg.ticket_id=t.ticket_id')
            ->addWhere('msg.type=?', 'message')
            ->leftJoin('?_user', 'm', 't.user_id=m.user_id')
            ->leftJoin('?_helpdesk_category', 'c', 't.category_id=c.category_id')
            ->addField('c.title', 'c_title')
            ->addOrder('updated', true);

        return $query;
    }

    public function getGridTitle() {
        return ___("Tickets");
    }
}