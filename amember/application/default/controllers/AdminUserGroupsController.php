<?php

class AdminUserGroupsController extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_SETUP);
    }
    function indexAction()
    {
        $this->view->groups = $this->getDi()->userGroupTable->getTree();
        $this->view->display('admin/user-groups.phtml');
    }
    function saveAction()
    {
        $this->_response->setHeader('Content-type', 'text/plain; charset=utf-8');
        $id = $this->getInt('user_group_id');
        if ($id) {
            $pc = $this->getDi()->userGroupTable->load($id);
        } else {
            $pc = $this->getDi()->userGroupRecord;
        }
        $pc->title = $this->getParam('title');
        $pc->description = $this->getParam('description');
        $pc->parent_id = $this->getInt('parent_id');
        $pc->sort_order = $this->getInt('sort_order');
        $pc->save();
        echo $this->getJson($pc->toArray());
    }
    function delAction()
    {
        $id = $this->getInt('id');
        if (!$id) throw new Am_Exception_InputError("Wrong id");
        $pc = $this->getDi()->userGroupTable->load($id);
        $this->getDi()->userGroupTable->moveNodes($pc->pk(), $pc->parent_id);
        $pc->delete();
        echo "OK";
    }
}