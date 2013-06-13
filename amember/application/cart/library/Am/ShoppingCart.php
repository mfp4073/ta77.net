<?php

class Am_ShoppingCart
{
    /** Invoice */
    protected $invoice;
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }
    function addItem($product, $qty)
    {
        $this->invoice->add($product, $qty);
    }
    function deleteItem($product) {
        if ($item = $this->getInvoice()->findItem('product', $product->pk()))
                $this->getInvoice()->deleteItem($item);
    }
    /** @return Invoice */
    function getInvoice()
    {
        return $this->invoice;
    }
    /**
     * @return array of InvoiceItem
     */
    function getItems()
    {
        return $this->invoice->getItems();
    }
    /**
     * @return Am_Currency
     */
    function getCurrency($amount)
    {
        return $this->invoice->getCurrency($amount);
    }
    function hasItem($product) {
        foreach($this->getItems() as $item) {
            if ($item->item_id == $product->pk()) return true;
        }
        return false;
    } 
    function getText()
    {
        $items = $this->invoice->getItems();
        if (!$this->invoice->getItems())
            return "You have no items in shopping cart";
        $c = count($items);
        return "You have $c items in shopping cart";
    }
    /**
     * @param string $code
     * @return null|string null if ok, or error message
     */
    function setCouponCode($code)
    {
        $this->invoice->setCouponCode($code);
        return $this->invoice->validateCoupon();
    }
    function getCouponCode()
    {
        $coupon = $this->invoice->getCoupon();
        if ($coupon) return $coupon->code;
    }
    function setUser(User $user)
    {
        $this->invoice->setUser($user);
    }
    function calculate()
    {
        $this->invoice->calculate();
    }
    function clear()
    {
        $this->invoice = null;
    }
}

