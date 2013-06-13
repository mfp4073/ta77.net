<?php

class Cart_AdminShoppingCartController extends Am_Controller_Pages {
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('carthtmlgenerate');
    }
    public function initPages() {
        $this->addPage(array($this, 'configCartController'), 'cart', ___('Shopping Cart Settings'))
            ->addPage(array($this, 'createButtonController'), 'button', ___('Button/Link HTML Code'))
            ->addPage(array($this, 'createBasketController'), 'basket', ___('Basket HTML Code'));
    }

    public function configCartController($id, $title, Am_Controller $controller) {
        return new AdminShoppingCart_Settings($controller->getRequest(), $controller->getResponse(), $this->_invokeArgs);
    }

    public function createButtonController($id, $title, Am_Controller $controller) {
        return new AdminCartHtmlGenerateController_Button($controller->getRequest(), $controller->getResponse(), $this->_invokeArgs);
    }

    public function createBasketController($id, $title, Am_Controller $controller) {
        return new AdminCartHtmlGenerateController_Basket($controller->getRequest(), $controller->getResponse(), $this->_invokeArgs);
    }
}


require_once APPLICATION_PATH . '/default/controllers/AdminSetupController.php';
class AdminShoppingCart_Settings extends AdminSetupController
{
    public function indexAction()
    {
        $this->_request->setParam('page', 'cart');

        $this->p = filterId($this->_request->getParam('page'));
        $this->initSetupForms();
        $this->form = $this->getForm($this->p, false);
        $this->form->prepare();
        if ($this->form->isSubmitted())
        {
            $this->form->setDataSources(array($this->_request));
            if ($this->form->validate() && $this->form->saveConfig()) {
                $this->redirectHtml($this->getUrl(), 'Config values updated...');
                return;
            }
        } else {
            $this->form->setDataSources(array(
                new HTML_QuickForm2_DataSource_Array($this->getConfigValues()),
                new HTML_QuickForm2_DataSource_Array($this->form->getDefaults()),
            ));
        }
        $this->view->assign('p', $this->p);
        $this->form->replaceDotInNames();
        
        $this->view->assign('pageObj', $this->form);
        $this->view->assign('form', $this->form);
        $this->view->display('cart/cart-settings.phtml');
    }
}

class AdminCartHtmlGenerateController_Button extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('carthtmlgenerate');
    }
    
    public function indexAction()
    {
        if ($this->getRequest()->getParam('title') && $this->getRequest()->getParam('actionType'))
        {
            if (!$this->getRequest()->getParam('productIds'))
            {
                $products = array();
                foreach ($this->getDi()->getInstance()->productTable->findBy() as $value)
                {
                    $products[] = $value->product_id;
                }

            } else
            {
                $products = $this->getRequest()->getParam('productIds');
            }
            $prIds = join(',', $products);
            $htmlcode = '
<!-- Button/Link for Amember Shopping Cart -->
<script type="text/javascript">
if (typeof cart  == "undefined")
    document.write("<scr" + "ipt src=\''.REL_ROOT_URL.'/application/cart/views/public/js/cart.js\'></scr" + "ipt>");
</script>
';
            if ($this->getRequest()->getParam('isLink'))
            {
                $htmlcode .= '<a href="#" onclick="cart.' . $this->getRequest()->getParam('actionType') . '(this,' . $prIds . '); return false;" >' . $this->getRequest()->getParam('title') . '</a>';
            } else
            {
                $htmlcode .= '<input type="button" onclick="cart.' . $this->getRequest()->getParam('actionType') . '(this,' . $prIds . '); return false;" value="' . $this->getRequest()->getParam('title') . '">';
            }
            $htmlcode .='
<!-- End button/link for amember shopping cart -->
';

            $this->view->assign('htmlcode', $htmlcode);
            $this->view->display('admin/cart/button-code.phtml');
        } else
        {
            $products = array();
            foreach ($this->getDi()->getInstance()->productTable->findBy() as $value)
            {
                $products[$value->product_id] = $value->title;
            }

            $form = new Am_Form_Admin();

            $form->addMagicSelect('productIds')
                ->setLabel("Select Product(s)\nif nothing selected - all products")
                ->loadOptions($products);

            $form->addSelect('isLink')
                ->setLabel('Select Type of Element')
                ->loadOptions(array(
                    0 => 'Button',
                    1 => 'Link',
                ));

            $form->addSelect('actionType')
                ->setLabel('Select Action of Element')
                ->loadOptions(array(
                    'addExternal' => 'Add to Basket only',
                    'addBasketExternal' => 'Add & Go to Basket',
                    'addCheckoutExternal' => 'Add & Checkout',
                ));

            $form->addText('title')
                ->setLabel('Title of Element')
                ->addRule('required');

            $form->addSaveButton('Generate');

            $this->view->assign('form', $form);
            $this->view->display('admin/cart/button-code.phtml');
        }
    }
}

class AdminCartHtmlGenerateController_Basket extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('carthtmlgenerate');
    }
    
    public function indexAction()
    {
            $htmlcode = '
<!-- Basket for Amember Shopping Cart -->
<script type="text/javascript">
if (typeof(cart) == "undefined")
    document.write("<scr" + "ipt src=\''.REL_ROOT_URL.'/application/cart/views/public/js/cart.js\'></scr" + "ipt>");
jQuery(function(){cart.loadOnly();});
</script>
<div class="am-block block"><div id="block-cart-basket"></div></div>
<!-- End basket for amember shopping cart -->
';

            $this->view->assign('htmlcode', $htmlcode);
            $this->view->display('admin/cart/basket-code.phtml');
    }
}

class Am_Form_Setup_Cart extends Am_Form_Setup
{

    public function __construct()
    {
        parent::__construct('cart');
        $this->setTitle(___('Shopping Cart'));
    }

    public function initElements()
    {
        $this->addElement('advcheckbox', 'cart.hide_default_billing_plan')
            ->setLabel(___("Do not show 'Default billing plan' on form\n" .
                    "if there is only one billing plan only terms will be listed"));

        $this->addElement('advcheckbox', 'cart.redirect_to_cart')
            ->setLabel(___("Redirect to signup cart page on default"));

        $this->addElement('advcheckbox', 'cart.show_menu_cart_button')
            ->setLabel(___("Hide 'Add/renew Subscription' button\n" .
                    "and show 'Shopping Cart' button"));
        
        $imgSize = $this->addGroup()
            ->setLabel(array('Product image width x height', 'Empty - 200x200 px'));
        
        $imgSize->addElement('text', 'cart.product_image_width', array('size' => 3))
            ->addRule('regex', ___('Image width must be number greater than 0.'), '/^$|^[1-9](\d+)?$/');
        $imgSize->addElement('text', 'cart.product_image_height', array('size' => 3))
            ->addRule('regex', ___('Image height must be number greater than 0.'), '/^$|^[1-9](\d+)?$/');
    }
}
