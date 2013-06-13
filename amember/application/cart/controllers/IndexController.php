<?php
// TODO:
//  * products browsing
//  * view product details if enabled
//  * basket view
//  * add coupon
//  * auth/register box
//  
//  checkout process:
//    - check empty basket
//
//  $this->doCheckEmptyBasket()
//  $this->doCheckUserLoggedInOrRedirectToSignup()
//  $this->doCheckPaysysAcceptable()
//  $this->doCheckPaysysChoosedOrChoose()
//  $this->doCheckAndDisplayConfirmation()
//  $this->doPaymentAndHandleResult())
//  $this->doShowThanksPage or $this->redirectToContent or $this->redirectToAccount
//
//  checkout process - unit tests
//  checkout process - handling failures
//  auth controller - redirects with unit tests
//
//  products search - 
//  
//  , description

/*
*  User's signup page
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Signup Page
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision: 4867 $)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class Cart_IndexController extends Am_Controller
{
    /** @var Am_ShoppingCart */
    protected $cart;
    /** @var Am_Query */
    protected $query;
    protected $hiddenCatCodes = array();

    public function init()
    {
        parent::init();
        $this->loadCart();
        $this->view->cart = $this->cart;
        
        $cc = $this->getCategoryCode();

        $cats = explode(',', $this->getRequest()->getCookie('am-cart-cats', ''));
        if (!in_array($cc, $cats)) {
            $cats[] = $cc;
            $this->setCookie('am-cart-cats', implode(',', $cats));
        }
        $this->hiddenCatCodes = $cats;

        $this->getDi()->blocks
        ->add(
            new Am_Block('cart/right', ___('Search Products'), 'cart-search', $this->getModule(), 'search.phtml', Am_Block::TOP)
        )->add(
            new Am_Block('cart/right', ___('Your Basket'), 'cart-basket', $this->getModule(), 'basket.phtml', Am_Block::TOP)
        )->add(
            new Am_Block('cart/right', ___('Authentication'), 'cart-auth', $this->getModule(), 'auth.phtml')
        );
    }
    public function getProductsQuery(ProductCategory $category = null)
    {
        if (!$this->query)
        {
            $this->query = $this->getDi()->productTable->createQuery($category ? $category->product_category_id : null, $this->getHiddenCatCodes());
        }
        
        return $this->query;
    }
    public function addFromRequest()
    {
        //data = [{id:id,qty:qty,plan:plan,type:type},{}...]
        $data = json_decode($this->getParam('data'));
        try{
            if (!$data)
                throw new Am_Exception_InternalError(___('Shopping Cart Module. No data input'));
        
            foreach ($data as $item)
            {
                $item_id = intval($item->id);
                if (!$item_id)
                    throw new Am_Exception_InternalError(___('Shopping Cart Module. No product id input'));
                $qty = (!empty($item->qty) && $q=intval($item->qty)) ? $q : 1;

                $p = $this->getDi()->productTable->load($item_id);
                if (!empty($item->plan) && $bp=intval($item->plan))
                    $p->setBillingPlan($bp);
                $this->cart->addItem($p, $qty);
            }

            if ($this->getDi()->auth->getUserId())
                $this->cart->setUser($this->getDi()->user);
            $this->cart->calculate();
        } catch (Exception $e)
        {
            $this->getDi()->getInstance()->errorLogTable->logException($e);
            $this->ajaxResponse(
                array(
                'status' => 'error',
                'message' => $e->getPublicError()
            ));
            return;
        }
        $this->ajaxResponse(array('status' => 'ok'));
    }
    public function ajaxAddAction()
    {
        $this->addFromRequest();
        $this->view->display('blocks/basket.phtml');
    }
    public function ajaxAddOnlyAction()
    {
        $this->addFromRequest();
    }
    public function ajaxLoadOnlyAction()
    {
        $this->view->display('blocks/basket.phtml');
    }
    public function addAndCheckoutAction()
    {
//        $this->addFromRequest();
        $this->checkoutAction();
    }
    public function choosePaysysAction()
    {
        $this->view->paysystems = array();
        foreach ($this->getDi()->paysystemList->getAll() as $ps)
        {
            $plugin = $this->getDi()->plugins_payment->get($ps->paysys_id);
            if (!($err = $plugin->isNotAcceptableForInvoice($this->cart->getInvoice())))
            {
                $this->view->paysystems[] = $ps;
                $enabled[] = $ps->getId();
            }
        }
        if (!$this->view->paysystems)
            throw new Am_Exception_InternalError("Sorry, no payment plugins enabled to handle this invoice");
        if ($paysys_id = $this->getFiltered('paysys_id'))
        {
            if (!in_array($paysys_id, $enabled))
                throw new Am_Exception_InputError("Sorry, paysystem [$paysys_id] is not available for this invoice");
            $this->cart->getInvoice()->setPaysystem($paysys_id);
            return $this->doCheckout();
        }
        if (count($this->view->paysystems) == 1)
        {
            $firstps = $this->view->paysystems[0];
            $this->cart->getInvoice()->setPaysystem($firstps->getId());
            return $this->doCheckout();
        }
        $this->view->display('cart/choose-paysys.phtml');
    }
    public function loginAction()
    {
        return $this->redirectLocation(REL_ROOT_URL . '/login?saved_form=cart&_amember_redirect_url=' . base64_encode($this->view->url()));
    }
    public function signupRedirect($url)
    {
        return $this->redirectLocation(REL_ROOT_URL . '/signup/index/c/cart?amember_redirect_url=' . urlencode($url));
    }
    private function doLoginOrSignup()
    {
        $pos = stripos($this->view->url(), $this->_request->getBaseUrl() . '/cart/');
        $url = substr($this->view->url(), $pos +  strlen($this->_request->getBaseUrl() . '/cart/'));
        $this->signupRedirect($url);
    }
    public function checkoutAction()
    {
        $this->cart->getInvoice()->paysys_id = null;
        return $this->doCheckout();

    }

    protected function doCheckout()
    {
        do {
            if (!$this->cart->getItems())
            {
                $errors[] = ___("You have no items in your basket - please add something to your basket before checkout");
                return $this->view->display('cart/basket.phtml');
            }
            if (!$this->getDi()->auth->getUserId())
                return $this->doLoginOrSignup();
            else
                $this->cart->setUser($this->getDi()->user);
            if (empty($this->cart->getInvoice()->paysys_id))
                return $this->choosePaysysAction();

            $invoice = $this->cart->getInvoice();
            $errors = $invoice->validate();
            if ($errors){
                $this->view->assign('errors', $errors);
                return $this->view->display('cart/basket.phtml');
            }
            // display confirmation
            if (!$this->getInt('confirm') && $this->getDi()->config->get('shop.confirmation'))
                return $this->view->display('cart/confirm.phtml');
            ///
            $invoice->save();
            
            $payProcess = new Am_Paysystem_PayProcessMediator($this, $invoice);
            $result = $payProcess->process();
            if ($result->isFailure())
            {
                $this->view->error = ___("Checkout error: ") . current($result->getErrorMessages());
                $this->cart->getInvoice()->paysys_id = null;
                $this->_request->set('do-checkout', null);
                return $this->viewBasketAction();
            }
        } while (false);
    }
    public function indexAction()
    {
        $category = $this->loadCategory();
        $this->view->category = $category;

        $userOptions = $this->getDi()->productCategoryTable->getUserSelectOptions(array(
                            ProductCategoryTable::EXCLUDE_EMPTY => true,
                            ProductCategoryTable::COUNT => true, 
                            ProductCategoryTable::EXCLUDE_HIDDEN => true,
                            ProductCategoryTable::INCLUDE_HIDDEN => $this->getHiddenCatCodes()
                            )
                        );
        $pCategory = array(null => '-- Select Category --');
        foreach ($userOptions as $k=>$v) {
            $pCategory[$k] = $v;
        }
        $this->view->productCategoryOptions = $pCategory;

        $query = $this->getProductsQuery($category);
        
        $count = $this->getConfig('records_on_page', 10);
        $page = $this->getInt('cp');
        $this->view->products = $query->selectPageRecords($page, $count);
        $total = $query->getFoundRows();
        $pages = floor($query->getFoundRows()/$count);
        if($pages*$count<$total) $pages++;
        $this->view->paginator = new Am_Paginator($pages, $page, null, 'cp');
        
        $this->view->display('cart/index.phtml');
    }
    public function searchAction()
    {
        if ($q = $this->getEscaped('q'))
        {
            $query = $this->getProductsQuery(null);
            $query->addWhere("(p.title LIKE ?) OR (p.description LIKE ?)", "%$q%", "%$q%");
        }
        return $this->indexAction();
    }
    public function productAction()
    {
        $id = $this->getInt('id');
        if ($id<=0) throw new Am_Exception_InputError("Invalid product id specified [$id]");
        $category = $this->loadCategory();
        $this->view->category = $category;
        $this->view->productCategoryOptions = array(null => '-- Select Category --') + $this->getDi()->productCategoryTable->getUserSelectOptions(array(
                            ProductCategoryTable::EXCLUDE_EMPTY => true,
                            ProductCategoryTable::COUNT => true,
                            ProductCategoryTable::EXCLUDE_HIDDEN => true,
                            ProductCategoryTable::INCLUDE_HIDDEN => $this->getHiddenCatCodes()
                            ));
        $query = $this->getProductsQuery($category);
        $query->addWhere("p.product_id=?d", $id);
        $productsFound = $query->selectPageRecords(0, 1);
        if (!$productsFound) throw new Am_Exception_InputError("Product #[$id] not found (category code [".$this->getCategoryCode()."])");
        $this->view->assign('product', current($productsFound));
        $this->view->display('cart/product.phtml');
    }
    public function viewBasketAction()
    {
        if ($this->getParam('do-return'))
        {
            if ($this->getParam('b'))
                return $this->redirectLocation($this->getParam('b'));
            return $this->_redirect('cart');
        }
        $d   = (array)$this->getParam('d', array());
        $qty = (array)$this->getParam('qty', array());
        foreach ($qty as $item_id => $newQty)
        {
            if ($item = $this->cart->getInvoice()->findItem('product', intval($item_id)))
                if ($item->is_countable && $item->variable_qty)
                {
                    if ($newQty == 0) {
                        $this->cart->getInvoice()->deleteItem($item);
                    } else {
                        $item->qty = 0;
                        $item->add($newQty);
                    }
                }
        }
        foreach ($d as $item_id => $val)
            if ($item = $this->cart->getInvoice()->findItem('product', intval($item_id)))
                $this->cart->getInvoice()->deleteItem($item);
        if (($code = $this->getFiltered('coupon')) !== null)
            $this->view->coupon_error = $this->cart->setCouponCode($code);
        if ($this->getDi()->auth->getUserId())
            $this->cart->setUser($this->getDi()->user);
        $this->cart->calculate();
        if (!$this->view->coupon_error && $this->getParam('do-checkout'))
            return $this->checkoutAction();
        $this->getDi()->blocks->remove('cart-basket');
        $this->view->b = $this->getParam('b','');
        $this->view->display('cart/basket.phtml');
    }
    public function getCategoryCode()
    {
        return $this->getFiltered('c', @$_GET['c']);
    }
    public function getHiddenCatCodes()
    {
        return $this->hiddenCatCodes;
    }
    public function loadCategory()
    {
        $code = $this->getCategoryCode();
        if ($code)
        {
            $category = $this->getDi()->productCategoryTable->findByCodeThenId($code);
            if (null == $category)
                throw new Am_Exception_InputError(___("Category [$code] not found"));
        } else
            $category = null;
        return $category;
    }
    public function loadCart()
    {
        $this->cart = @$this->getSession()->cart;
        if ($this->cart && $this->cart->getInvoice()->isCompleted())
            $this->cart = null;
        if (!$this->cart)
        {
            $this->cart = new Am_ShoppingCart($this->getDi()->invoiceRecord);
            /** @todo not serialize internal data in Invoice class */
            $this->getSession()->cart = $this->cart;
        }
        if ($this->getDi()->auth->getUserId())
            $this->cart->setUser($this->getDi()->user);
        $this->cart->getInvoice()->calculate();
    }
    public function getCart()
    {
        return $this->cart;
    }

}
