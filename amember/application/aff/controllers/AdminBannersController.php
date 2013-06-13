<?php 
/*
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Affiliate commission
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision$)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*                                                                          
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class Am_Grid_Editable_Downloads extends Am_Grid_Editable {
    protected $prefix = 'affiliate';
    protected $permissionId = 'affiliates';

    public function __construct(Am_Request $request, Am_View $view) {
        if (!Am_Di::getInstance()->uploadAcl->checkPermission($this->prefix, 
                Am_Upload_Acl::ACCESS_ALL, 
                Am_Di::getInstance()->authAdmin->getUser())) {
            
            throw new Am_Exception_AccessDenied();
        }
        $id = explode('_', get_class($this));
        $id = strtolower(array_pop($id));
        parent::__construct('_'.$id, ___('Marketing Materials'), $this->createDs(), $request, $view);
    }

    function init() {
        $this->setRecordTitle('File');
        $this->setFilter(new Am_Grid_Filter_Text(___('Filter by name or description'), array('name'=>'LIKE', 'desc'=>'LIKE')));
    }

    protected function createDs() {
        $ds = new Am_Query(Am_Di::getInstance()->uploadTable);
        $ds->addWhere('prefix=?', $this->prefix);
        return $ds;
    }

    function initActions() {
        $this->actionAdd(new Am_Grid_Action_Upload());
        $this->actionAdd(new Am_Grid_Action_Delete());

        $actionDownload = new Am_Grid_Action_Url('download', ___('Download'), Am_Controller::makeUrl('admin-upload', 'get', 'default', array(
                'id' => '__ID__'
        )));
        $actionDownload->setTarget('_top');
        $this->actionAdd($actionDownload);
        $this->actionAdd(new Am_Grid_Action_Group_Delete());
        $this->actionAdd(new Am_Grid_Action_LiveEdit('desc'));
    }

    protected function initGridFields() {
        $this->addField(new Am_Grid_Field('name', ___('Name'), true));
        $this->addField(new Am_Grid_Field('desc', ___('Description'), true));
        parent::initGridFields();
    }

    public function createForm() {
        $form = new Am_Form_Admin();
        $form->setAttribute('enctype', 'multipart/form-data');
        $file = $form->addElement('file', 'upload[]')
                ->setLabel('File')
                ->setAttribute('class', 'styled');
        $file->addRule('required');
        $form->addText('desc')
                ->setLabel(___('Description'));
        $form->addHidden('prefix')->setValue($this->prefix);

        return $form;
    }

}

abstract class Am_Grid_Editable_AffBannersAbstract extends Am_Grid_Editable {
    protected $affBannerType = null;
    protected $permissionId = 'affiliates';

    public function __construct(Am_Request $request, Am_View $view) {
        $id = explode('_', get_class($this));
        $id = strtolower(array_pop($id));
        parent::__construct('_'.$id, $this->getGridTitle(), $this->createDs(), $request, $view);
    }

    abstract protected function getGridTitle();

    protected function initGridFields() {
        $this->addField(new Am_Grid_Field('title', ___('Title'), true, '', null, '25%'));
        $this->addField(new Am_Grid_Field('url', ___('URL'), true, '', null, '35%'));
        $this->addField(new Am_Grid_Field('sort_order', ___('Sort Order')));
        $this->addGridField(new Am_Grid_Field('available', ___('Available'), false))->setRenderFunction(array($this, 'renderUGroup'));
        $this->addField(new Am_Grid_Field_IsDisabled());
        $this->actionAdd(new Am_Grid_Action_LiveEdit('sort_order'));
        parent::initGridFields();
    }

    protected function createDs() {
        $query = new Am_Query(Am_Di::getInstance()->affBannerTable);
        $query->addWhere('type=?', $this->affBannerType);

        return $query;
    }
    function createForm() {
        $form = new Am_Form_Admin;
        $form->addMagicselect('user_group_id')
            ->setLabel(___('Available for users from groups') . "\n" . ___('leave it empty in case of you want this item be available for all users'))
            ->loadOptions($this->getDi()->userGroupTable->getSelectOptions());

        $text =$form->addElement('text', 'title', array('size' => 60))
                ->setLabel(___('Title'));
        $text->addRule('required');

        $url = $form->addElement('text', 'url', array('size' => 60))
                ->setLabel(___('Redirect URL'));
        $url->addRule('required');

        $form->addElement('textarea', 'desc', array('rows'=>10, 'style'=>'width:90%'))
                ->setLabel(array(___('Description'), ''));
        $form->addElement('hidden', 'type')
                ->setValue($this->affBannerType);
        return $form;
    }

    function valuesFromForm() {

        $values = parent::valuesFromForm();
        $values['user_group_id'] = implode(',', @$values['user_group_id']);
        return $values;
    }


    function valuesToForm() {
        $values = parent::valuesToForm();
        $values['user_group_id'] = explode(',', @$values['user_group_id']);
        return $values;
    }

    function renderUGroup($b)
    {
        $res = array();
        $options = $this->getDi()->userGroupTable->getSelectOptions();
        foreach (explode(',', $b->user_group_id) as $ug_id)
        {
            if (isset($options[$ug_id]))
                $res[] = $options[$ug_id];
        }
        return $this->renderTd($res ? implode(", ", $res) : ___('All'));
    }



}

class Am_Grid_Editable_Banners extends Am_Grid_Editable_AffBannersAbstract {
    protected $affBannerType = AffBanner::TYPE_BANNER;

    protected function getGridTitle() {
        return 'Banners';
    }

    function createForm() {
        $form = parent::createForm();
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->setAttribute('target', '_top');
        $upload_id = $form->addElement(new Am_Form_Element_Upload('upload_id', array(), array('prefix'=>'banners')))
                ->setLabel(___('Image'))
                ->setId('banners-upload_id')
                ->setAllowedMimeTypes(array(
                    'image/png', 'image/jpeg', 'image/tiff', 'image/gif',
                ));

        $jsOptions = <<<CUT
{
onFileAdd : function (info) {

        var width = $(this).closest("form").find("input[name='size[width]']");
        var height = $(this).closest("form").find("input[name='size[height]']");
        $.get(window.rootUrl + '/admin-upload/get-size', {'id' : info.upload_id}, function(data, textStatus){;
            data = $.parseJSON(data);
            if (textStatus == 'success' && data) {
                width.val(data.width);
                height.val(data.height);
            }
        });
     }
}
CUT;
        $upload_id->setJsOptions($jsOptions);


        $upload_id->addRule('required');

        $size = $form->addElement('group', 'size')
                ->setLabel(array(___('Size'), ___('Width') . ' &times ' . ___('Height')));

        $width = $size->addElement('text', 'width', array('size' => 10));
        $height = $size->addElement('text', 'height', array('size' => 10));


        return $form;
    }

    function valuesFromForm() {

        $values = parent::valuesFromForm();

        $values['height'] = $values['size']['height'];
        $values['width'] = $values['size']['width'];
        unset($values['size']);

        return $values;
    }


    function valuesToForm() {
        $values = parent::valuesToForm();

        $values['size']['height'] = @$values['height'];
        $values['size']['width'] = @$values['width'];

        return $values;
    }
}

class Am_Grid_Editable_TextLinks extends Am_Grid_Editable_AffBannersAbstract {
    protected $affBannerType = AffBanner::TYPE_TEXTLINK;

    protected function getGridTitle() {
        return ___('Text Links');
    }
}

class Am_Grid_Editable_Custom extends Am_Grid_Editable_AffBannersAbstract {
    protected $affBannerType = AffBanner::TYPE_CUSTOM;

    protected function getGridTitle() {
        return ___('Custom HTML');
    }

    function createForm() {
        $form = parent::createForm();
        $form->addElement('textarea', 'html', array('rows'=>10, 'style'=>'width:90%'))
                ->setLabel(array(___('HTML Code'), ___('%url% will be replaced with actual url of affilate link')))
                ->addRule('required');
        return $form;
    }
}

//class Am_Grid_Editable_PagePeels extends Am_Grid_Editable_AffBannersAbstract {
//    protected $affBannerType = AffBanner::TYPE_PAGEPEEL;
//
//    protected function getGridTitle() {
//        return ___('Page Peels');
//    }
//
//    function createForm() {
//        $form = parent::createForm();
//        $form->setAttribute('enctype', 'multipart/form-data');
//        $form->setAttribute('target', '_top');
//        $upload_id = $form->addElement(new Am_Form_Element_Upload('upload_id', array(), array('prefix'=>'banners')))
//                ->setLabel(array(___('Small Peel Image'), '75 &times; 75'))
//                ->setId('pagepeels-upload_id');
//        $upload_id->addRule('required');
//
//        $upload_big_id = $form->addElement(new Am_Form_Element_Upload('upload_big_id', array(), array('prefix'=>'banners')))
//                ->setLabel(array(___('Large Peel Image'), '500 &times; 500'))
//                ->setId('pagepeels-upload_big_id');
//        $upload_big_id->addRule('required');
//
//        return $form;
//    }
//}

class Am_Grid_Editable_LightBoxes extends Am_Grid_Editable_AffBannersAbstract {
    protected $affBannerType = AffBanner::TYPE_LIGHTBOX;

    protected function getGridTitle() {
        return ___('Light Boxes');
    }

    function createForm() {
        $form = parent::createForm();
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->setAttribute('target', '_top');
        $upload_id = $form->addElement(new Am_Form_Element_Upload('upload_id', array(), array('prefix'=>'banners')))
                ->setLabel(array(___('Lightbox Thumbnail Image')))
                ->setId('lightboxes-upload_id')
                ->setAllowedMimeTypes(array(
                    'image/png', 'image/jpeg', 'image/tiff', 'image/gif',
                ));
        $upload_id->addRule('required');

        $upload_big_id = $form->addElement(new Am_Form_Element_Upload('upload_big_id', array(), array('prefix'=>'banners')))
                ->setLabel(array(___('Lightbox Main Image')))
                ->setId('lightboxes-upload_big_id')
                ->setAllowedMimeTypes(array(
                    'image/png', 'image/jpeg', 'image/tiff', 'image/gif',
                ));
        $upload_big_id->addRule('required');


        return $form;
    }

    function valuesFromForm() {

        $values = parent::valuesFromForm();

        $values['height'] = $values['size']['height'];
        $values['width'] = $values['size']['width'];
        unset($values['size']);

        return $values;
    }


    function valuesToForm() {
        $values = parent::valuesToForm();

        $values['size']['height'] = @$values['height'];
        $values['size']['width'] = @$values['width'];

        return $values;
    }
}

class Am_Grid_Action_Upload extends Am_Grid_Action_Abstract {
    protected $type = self::NORECORD; // this action does not operate on existing records
    
    public function __construct($id = null, $title = null)
    {
        $this->title = ___("Upload");
        parent::__construct($id, $title);
    }
    
    
    public function run() {
        $form = $this->grid->getForm();
        $form->setAttribute('target', '_top');
        $upload = new Am_Upload(Am_Di::getInstance());
        $upload->setPrefix($this->grid->getCompleteRequest()->getParam('prefix'));
        $upload->loadFromStored();
        $ids_before = $this->getUploadIds($upload);

        if ($form->isSubmitted() && $upload->processSubmit('upload')) {
            //find currently uploaded file
            $upload_id = array_pop(array_diff($this->getUploadIds($upload), $ids_before));
            $upload = Am_Di::getInstance()->uploadTable->load($upload_id);
            $upload->desc = $this->grid->getCompleteRequest()->getParam('desc');
            $upload->save();

            return $this->grid->redirectBack();
        }

        echo $this->renderTitle();
        echo $form;
    }

    protected function getUploadIds(Am_Upload $upload) {
        $upload_ids = array();
        foreach($upload->getUploads() as $upload) {
            $upload_ids[] = $upload->pk();
        }
        return $upload_ids;
    }
}

class Aff_AdminGeneralLinkController extends Am_Controller {

    public function checkAdminPermissions(Admin $admin) {
        return $admin->hasPermission('affiliates');
    }

    function indexAction()
    {
        $form = $this->createForm();
        $form->setDataSources(array($this->getRequest()));

        if ($this->getRequest()->isPost() && $form->validate()) {
            $v = $form->getValue();
            $this->getDi()->config->saveValue('aff.general_link_url', $v['general_link_url']);
        }

        echo $form;
    }

    function createForm()
    {
        $form = new Am_Form_Admin('aff-general-link');
        $form->addElement('text', 'general_link_url', array (
               'size' => 40,
             ))
            ->setLabel(___('General Affiliate Link Redirect URL'))
            ->setValue($this->getDi()->config->get('aff.general_link_url', ''));
        $form->addSaveButton();
        return $form;
    }
}

class Aff_AdminBannersController extends Am_Controller_Pages {

    public function checkAdminPermissions(Admin $admin) {
        return $admin->hasPermission('affiliates');
    }

    public function preDispatch() {
        parent::preDispatch();
        $this->setActiveMenu('affiliates-banners');
    }

    public function initPages() {
            $this->addPage(array($this, 'createController'), 'general', ___('General Link'))
                ->addPage('Am_Grid_Editable_Banners', 'banners', ___('Banners'))
                ->addPage('Am_Grid_Editable_TextLinks', 'textlinks', ___('Text Links'))
                //->addPage('Am_Grid_Editable_PagePeels', 'pagePeels', ___('Page Peels'))
                ->addPage('Am_Grid_Editable_LightBoxes', 'lightboxes', ___('Light Boxes'))
                ->addPage('Am_Grid_Editable_Custom', 'custom', ___('Custom HTML'))
                ->addPage('Am_Grid_Editable_Downloads', 'downloads', ___('Marketing Materials'));
    }

    public function createController($id, $title, $grid) {
        return new Aff_AdminGeneralLinkController($grid->getRequest(), $grid->getResponse(), $this->_invokeArgs);
    }
}