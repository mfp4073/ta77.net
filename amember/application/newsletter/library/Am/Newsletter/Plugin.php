<?php

abstract class Am_Newsletter_Plugin extends Am_Plugin
{
    protected $_configPrefix = 'newsletter.';
    protected $_idPrefix = 'Am_Newsletter_Plugin_';
    
    /**
     * Method must subscribe user to $addLists and unsubscribe from $deleteLists
     */
    abstract function changeSubscription(User $user, array $addLists, array $deleteLists);
    
    /**
     * Method must change customer e-mail when user changes it in aMember UI 
     */
    function changeEmail(User $user, $oldEmail, $newEmail)
    {
    }
    
    /** @return array lists 'id' => array('title' => 'xxx', )*/
    function getLists() { }
    /** 
     *  @return true if plugin can return lists (getLists overriden)
     */
    function canGetLists()
    {
        $rm = new ReflectionMethod($this, 'getLists');
        return ($rm->getDeclaringClass()->getName() !== __CLASS__);
    }       
    
    public function deactivate()
    {
        parent::deactivate();
        foreach ($this->getDi()->newsletterListTable->findByPluginId($this->getId()) as $list)
            $list->disable();
    }
    
    public function onUserAfterUpdate(Am_Event_UserAfterUpdate $event)
    {
    }
}
