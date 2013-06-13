<?php

class Bootstrap_Cart extends Am_Module 
{
    function onSavedFormTypes(Am_Event $event)
    {
        $event->getTable()->addTypeDef(array(
            'type' => SavedForm::T_CART,
            'title' => 'Shopping Cart Signup',
            'class' => 'Am_Form_Signup_Cart',
            'defaultTitle' => 'Create Customer Profile',
            'defaultComment' => 'shopping cart signup form',
            'isSingle' => true,
            'noDelete' => true,
            'urlTemplate' => 'signup/index/c/cart',
        ));
    }
    public function deactivate()
    {
        unset($this->getDi()->session->cart);
        parent::deactivate();
    }
    public function onAdminMenu(Am_Event $event)
    {
        $event->getMenu()->addPage(array(
            'id' => 'cart',
            'controller' => 'admin-shopping-cart',
            'action' => 'index',
            'module' => 'cart',
            'label' => 'Shopping Cart',
            'resource' => 'grid_carthtmlgenerate'
        ));
    }
    function onUserMenu(Am_Event $event)
    {
        if ($this->getDi()->getInstance()->config->get('cart.show_menu_cart_button'))
        {
            $menu = $event->getMenu();
            $menu->addPage(array(
                'id' => 'cart',
                'controller' => 'index',
                'module' => 'cart',
                'action' => 'index',
                'label' => ___('Shopping Cart'),
                'order' => 150,
            ));
            $page = $menu->findOneBy('id', 'add-renew');
            if ($page) $menu->removePage($page);
        }
    }
    function init()
    {
        parent::init();
        $this->getDi()->uploadTable->defineUsage(
            'product-img-cart',
            'product',
            'img',
            UploadTable::STORE_FIELD,
            "Image for product: [%title%]", '/admin-products?_product_a=edit&_product_id=%product_id%'
        );
    }
    function onGridProductInitForm(Am_Event $event)
    {
        if (!$formField=$event->getGrid()->getForm()->getElementById('additional'))
        {
            $formField=$event->getGrid()->getForm()
                ->addAdvFieldset('additional')
                ->setLabel(___('Additional'));
        }
        $formField->addUpload('img', null, array('prefix' => 'product-img-cart'))
            ->setLabel(___('Product Picture') . "\n" . ___('displayed on the shopping cart page'))
            ->setAllowedMimeTypes(array(
                    'image/png', 'image/jpeg', 'image/gif',
            ));

        $formField->addGroup('', array('id'=>'body-group'))
            ->setLabel(___('Product Description') . "\n" . ___('displayed on the shopping cart page'))
            ->addElement('textarea', 'cart_description', array('id'=>'product-decription-cart-0', 'rows'=>'15', 'cols'=>'80', 'style' => 'width: 90%;'));
        
        $formField->addScript('_bodyscript')->setScript(<<<CUT
$(function(){
    initCkeditor("product-decription-cart-0");
});            
CUT
        );
    }

    function onGridProductAfterSave(Am_Event $event)
    {
        $product = $event->getGrid()->getRecord();
        $vars = $event->getGrid()->getForm()->getValue();
        
        if (empty($vars['img']))
        {
            $product->img = null;
            $product->img_path = null;
            $product->update();
            return;
        }
        
        if ($product->img)
        {
            $height = $this->getDi()->getInstance()->config->get('cart.product_image_height', 200);
            $width = $this->getDi()->getInstance()->config->get('cart.product_image_width', 200);

            $upload = $this->getDi()->uploadTable->load($product->img);
            if ($upload->prefix != 'product-img-cart')
                throw new Am_Exception_InputError('Incorrect prefix requested [%s]', $upload->prefix);

            $filename = ROOT_DIR . '/data/' . $upload->path;
            switch ($mime = $upload->getType())
            {
                case 'image/gif' :
                    $handler = @imagecreatefromgif($upload->getFullPath());
                    $ext = 'gif';
                    break;
                case 'image/png' :
                    $handler = @imagecreatefrompng($upload->getFullPath());
                    $ext = 'png';
                    break;
                case 'image/jpeg' :
                    $handler = @imagecreatefromjpeg($upload->getFullPath());
                    $ext = 'jpg';
                    break;
                default :
                    throw new Am_Exception_InputError(sprintf('Unknown MIME type [%s]', $mime));
            }
            if (false === $handler)
            {
                //fix existing uploads
                $handler = @imagecreatefrompng($upload->getFullPath());
                if (false === $handler)
                    throw new Am_Exception_InputError(sprintf('Can not open [%s] as image resource', $upload->getPath()));
                else
                {
                    $upload->mime = 'image/png';
                    $upload->name = preg_replace('/\.[^.]+$/i', '.png', $upload->name);
                    $ext = 'png';
                    $upload->update();
                }
            }

            if (imagesy($handler) != $height || imagesx($handler) != $width)
            {
                $handler_result = $this->resize($handler, $width, $height);
                switch ($ext)
                {
                    case 'gif':
                        imagegif($handler_result, $filename);
                        break;
                    case 'jpg':
                        imagejpeg($handler_result, $filename);
                        break;
                    default:
                        imagepng($handler_result, $filename);
                        break;
                }
                
                imagedestroy($handler);
                imagedestroy($handler_result);
                $upload->mime = "image/$ext";
                $upload->name = preg_replace('/\.[^.]+$/i', '.png', $upload->name);
                $upload->update();
            }
            if (!@copy($filename, ROOT_DIR . '/data/public/' . $upload->path . ".$ext"))
                throw new Am_Exception_InternalError("Wrong permissions for " . ROOT_DIR . "/data/public/ folder. It's need 777");
            $product->img_path = $upload->path . ".$ext";
            $product->update();
        }
    }

    private function resize($handler, $width, $height)
    {
        $src_height = imagesy($handler);
        $src_width = imagesx($handler);

        $q = max($width / $src_width, $height / $src_height);
        $n_width = $src_width * $q;
        $n_height = $src_height * $q;

        $dist_x = $dist_y = 0;
        if ($n_width < $width)
            $dist_x = floor(($width - $n_width) / 2);
        else
            $dist_x = -1 * floor(($n_width - $width) / 2);

        if ($n_height < $height)
            $dist_y = floor(($height - $n_height) / 2);
        else
            $dist_y = -1 * floor(($n_height - $height) / 2);

        $result_handler = imagecreatetruecolor($width, $height);
        imagecopyresampled($result_handler, $handler, $dist_x, $dist_y, 0, 0, $n_width, $n_height, $src_width, $src_height);
        return $result_handler;
    }

    function onGetUploadPrefixList(Am_Event $event)
    {
        $event->addReturn(array(
            Am_Upload_Acl::IDENTITY_TYPE_ADMIN => Am_Upload_Acl::ACCESS_ALL,
            Am_Upload_Acl::IDENTITY_TYPE_USER => Am_Upload_Acl::ACCESS_READ,
            Am_Upload_Acl::IDENTITY_TYPE_ANONYMOUS => Am_Upload_Acl::ACCESS_READ
        ), 'product-img-cart');
    }

    function onDbUpgrade(Am_Event $e)
    {
        if (version_compare($e->getVersion(), '4.2.16') < 0)
        {
            $nDir = opendir(ROOT_DIR . '/data/');
            $baseDir = ROOT_DIR . '/data/';
            while (false !== ( $file = readdir($nDir) ))
                if (preg_match('/^.product-img-cart.*$/', $file, $matches) && !file_exists($baseDir.'public/'.$matches[0] . ".png"))
                    if (!@copy($baseDir.$matches[0], $baseDir.'public/'.$matches[0] . ".png"))
                        echo sprintf('<span style="color:red">Could not copy file [%s] to [%s]. Please, copy and rename manually.</span><br />',
                            $baseDir.$matches[0], $baseDir.'public/'.$matches[0] . ".png");

            closedir($nDir);
            $this->getDi()->db->query("
                UPDATE ?_product
                SET img_path = CONCAT(img_path,'.png')
                WHERE
                    img IS NOT NULL
                    AND img_path NOT LIKE '%.png'
                    AND img_path NOT LIKE '%.jpg'
                    AND img_path NOT LIKE '%.gif'
            ");
        }
    }

}