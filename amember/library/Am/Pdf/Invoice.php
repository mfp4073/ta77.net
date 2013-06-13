<?php

if (!@class_exists('Zend_Pdf_Page', true))
    include_once('Zend/Pdf_Pack.php');

/**
 * @package Am_Pdf  
 */
class Am_Pdf_Invoice
{

    /** @var Invoice */
    protected $invoice;
    /** @var InvoicePayment */
    protected $payment;
    /** @var Am_Di */
    protected $di = null;
    /** @var int */
    protected $pointer;

    const PAPER_FORMAT_LETTER = Zend_Pdf_Page::SIZE_LETTER;
    const PAPPER_FORMAT_A4 = Zend_Pdf_Page::SIZE_A4;

    function __construct(InvoicePayment $payment)
    {
        $this->invoice = $payment->getInvoice();
        $this->payment = $payment;
    }

    function setDi(Am_Di $di)
    {
        $this->di = $di;
    }

    /**
     *
     * @return Am_Di
     */
    function getDi()
    {
        return $this->di ? $this->di : Am_Di::getInstance();
    }

    protected function getPaperWidth()
    {
        return $this->getDi()->config->get('invoice_format', self::PAPER_FORMAT_LETTER) == self::PAPER_FORMAT_LETTER ?
            Am_Pdf_Page_Decorator::PAGE_LETTER_WIDTH :
            Am_Pdf_Page_Decorator::PAGE_A4_WIDTH;
    }

    protected function getPaperHeight()
    {
        return $this->getDi()->config->get('invoice_format', self::PAPER_FORMAT_LETTER) == self::PAPER_FORMAT_LETTER ?
            Am_Pdf_Page_Decorator::PAGE_LETTER_HEIGHT :
            Am_Pdf_Page_Decorator::PAGE_A4_HEIGHT;
    }

    protected function drawDefaultTemplate(Zend_Pdf $pdf)
    {
        $pointer = $this->getPaperHeight() - 20;

        $page = new Am_Pdf_Page_Decorator($pdf->pages[0]);
        if (!($ic = $this->getDi()->config->get('invoice_contacts')))
        {
            $ic = $this->getDi()->config->get('site_title') . '<br>' . $this->getDi()->config->get('root_url');
        }

        $page->setFont($this->getFontRegular(), 10);

        $invoice_logo_id = $this->getDi()->config->get('invoice_logo');
        if ($invoice_logo_id && ($upload = $this->getDi()->uploadTable->load($invoice_logo_id, false)))
        {
            $image = null;

            switch ($upload->getType())
            {
                case 'image/png' :
                    $image = new Zend_Pdf_Resource_Image_Png($upload->getFullPath());
                    break;
                case 'image/jpeg' :
                    $image = new Zend_Pdf_Resource_Image_Jpeg($upload->getFullPath());
                    break;
                case 'image/tiff' :
                    $image = new Zend_Pdf_Resource_Image_Tiff($upload->getFullPath());
                    break;
            }

            if ($image)
            {
                $page->drawImage($image, 20, $pointer - 100, 220, $pointer);
            }
        }

        $page->drawTextWithFixedWidth($ic, $this->getPaperWidth() - 20, $pointer, 400, null, Am_Pdf_Page_Decorator::ALIGN_RIGHT);
        $pointer-=110;
        $page->drawLine(20, $pointer, $this->getPaperWidth() - 20, $pointer);
        $page->nl($pointer);
        $page->nl($pointer);

        return $pointer;
    }

    /**
     *
     * @return Zend_Pdf
     *
     */
    protected function createPdfTemplate()
    {
        if ($this->getDi()->config->get('invoice_custom_template') &&
            ($upload = $this->getDi()->uploadTable->load($this->getDi()->config->get('invoice_custom_template'))))
        {
            $pdf = Zend_Pdf::load($upload->getFullPath());

            $this->pointer = $this->getPaperHeight() - $this->getDi()->config->get('invoice_skip', 150);
        }
        else
        {
            $pdf = new Zend_Pdf();
            $pdf->pages[0] = $pdf->newPage($this->getDi()->config->get('invoice_format', Zend_Pdf_Page::SIZE_LETTER));

            $this->pointer = $this->drawDefaultTemplate($pdf);
        }

        return $pdf;
    }

    //can be called only after createPdfTemplate
    protected function getPointer()
    {
        return $this->pointer;
    }

    protected function getFontRegular()
    {
        if ($this->getDi()->config->get('invoice_custom_ttf') &&
            ($upload = $this->getDi()->uploadTable->load($this->getDi()->config->get('invoice_custom_ttf'))))
                return Zend_Pdf_Font::fontWithPath($upload->getFullPath());
        else return Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA);
    }

    protected function getFontBold()
    {
        if ($this->getDi()->config->get('invoice_custom_ttfbold') &&
            ($upload = $this->getDi()->uploadTable->load($this->getDi()->config->get('invoice_custom_ttfbold'))))
                return Zend_Pdf_Font::fontWithPath($upload->getFullPath());
        else return Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA_BOLD);
    }

    public function getFileName()
    {
        $filename = $this->getDi()->config->get('invoice_filename', 'amember-invoice-%public_id%.pdf');
        $filename = str_replace('%public_id%', $this->invoice->public_id, $filename);
        $filename = str_replace('%receipt_id%', $this->payment->receipt_id, $filename);
        return $filename;
    }

    protected function isFirstPayment()
    {
        return $this->payment->isFirst();
    }

    public function render()
    {
        $invoice = $this->invoice;
        $payment = $this->payment;
        $user = $invoice->getUser();

        $pdf = $this->createPdfTemplate();

        $width_qty = 40;
        $width_price = 80;
        $width_total = 120;

        $padd = 20;
        $left = $padd;
        $right = $this->getPaperWidth() - $padd;

        $fontH = $this->getFontRegular();
        $fontHB = $this->getFontBold();

        $styleBold = array(
            'font' => array(
                'face' => $fontHB,
                'size' => 10
            ));

        $page = new Am_Pdf_Page_Decorator($pdf->pages[0]);
        $page->setFont($fontH, 10);

        $pointer = $this->getPointer();
        $pointerL = $pointerR = $pointer;

        $leftCol = new stdClass();
        $leftCol->invoiceNumber = ___('Invoice Number: ') . $invoice->public_id . '/' . $payment->receipt_id;
        $leftCol->date = ___('Date: ') . amDate($payment->dattm);
        if ($user->tax_id) {
            $leftCol->taxId = ___('EU VAT ID: ') . $user->tax_id;
        }

        $this->getDi()->hook->call(Am_Event::PDF_INVOICE_COL_LEFT, array(
            'col' => $leftCol,
            'invoice' => $invoice,
            'payment' => $payment,
            'user' => $user
        ));

        foreach ($leftCol as $line) {
            $page->drawText($line, $left, $pointerL);
            $page->nl($pointerL);
        }

        $rightCol = new stdClass();
        $rightCol->name = $invoice->getName();
        $rightCol->email = $invoice->getEmail();
        $rightCol->address = implode(', ',array_filter(array($invoice->getStreet(), $invoice->getCity())));
        $rightCol->country = implode(', ', array_filter(array($this->getState($invoice),
                    $invoice->getZip(),
                    $this->getCountry($invoice))));

        $this->getDi()->hook->call(Am_Event::PDF_INVOICE_COL_RIGHT, array(
            'col' => $rightCol,
            'invoice' => $invoice,
            'payment' => $payment,
            'user' => $user
        ));

        foreach ($rightCol as $line) {
            $page->drawText($line, $right, $pointerR, 'UTF-8', Am_Pdf_Page_Decorator::ALIGN_RIGHT);
            $page->nl($pointerR);
        }

        $pointer = min($pointerR, $pointerL);

        $p = new stdClass();
        $p->value = & $pointer;

        $this->getDi()->hook->call(Am_Event::PDF_INVOICE_BEFORE_TABLE, array(
            'page' => $page,
            'pointer' => $p,
            'invoice' => $invoice,
            'payment' => $payment,
            'user' => $user
        ));

        if ($this->getDi()->config->get('invoice_include_access')) {
            $pointer = $this->renderAccess($page, $pointer);
            $page->nl($pointer);
        }

        $table = new Am_Pdf_Table();
        $table->setMargin($padd, $padd, $padd, $padd);
        $table->setStyleForRow(
            1, array(
            'shape' => array(
                'type' => Zend_Pdf_Page::SHAPE_DRAW_FILL_AND_STROKE,
                'color' => new Zend_Pdf_Color_Html("#cccccc")
            ),
            'font' => array(
                'face' => $fontHB,
                'size' => 10
            )
            )
        );

        $table->setStyleForColumn( //qty
            2, array(
            'align' => 'right',
            'width' => $width_qty
            )
        );
        $table->setStyleForColumn( //price
            3, array(
            'align' => 'right',
            'width' => $width_price
            )
        );
        $table->setStyleForColumn( //total
            4, array(
            'align' => 'right',
            'width' => $width_total
            )
        );

        $table->addRow(array(
            ___('Subscription/Product Title'),
            ___('Qty'),
            ___('Unit Price'),
            ___('Total')
        ));

        foreach ($invoice->getItems() as $p)
        {
            /* @var $p InvoiceItem */
            $table->addRow(array(
                $p->item_title,
                $p->qty,
                $invoice->getCurrency($this->isFirstPayment() ? $p->first_price : $p->second_price),
                $invoice->getCurrency($this->isFirstPayment() ? $p->getFirstSubtotal() : $p->getSecondSubtotal())
            ));
        }

        $pointer = $page->drawTable($table, 0, $pointer);
        $page->nl($pointer);

        $table = new Am_Pdf_Table();
        $table->setMargin($padd, $padd, $padd, $padd);

        $table->setStyleForColumn(
            2, array(
            'align' => 'right',
            'width' => $width_total
            )
        );

        $table->addRow(array(
            ___('Subtotal'),
            $invoice->getCurrency($this->isFirstPayment() ? $invoice->first_subtotal : $invoice->second_subtotal)
        ))->addStyle($styleBold);

        if ( ($this->isFirstPayment() && $invoice->first_discount > 0) ||
            (!$this->isFirstPayment() && $invoice->second_discount > 0))
        {
            $table->addRow(array(
                ___('Coupon Discount'),
                $invoice->getCurrency($this->isFirstPayment() ? $invoice->first_discount : $invoice->second_discount)
            ));
        }

        if (($this->isFirstPayment() && $invoice->first_tax > 0) ||
            (!$this->isFirstPayment() && $invoice->second_tax > 0))
        {
            $table->addRow(array(
                ___('Tax Amount'),
                $invoice->getCurrency($this->isFirstPayment() ? $invoice->first_tax : $invoice->second_tax)
            ));
        }

        $table->addRow(array(
            ___('Total'),
            $invoice->getCurrency($this->isFirstPayment() ? $invoice->first_total : $invoice->second_total)
        ))->addStyle($styleBold);

        $x = $this->getPaperWidth() - ($width_qty + $width_price + $width_total) - 2 * $padd;
        $pointer = $page->drawTable($table, $x, $pointer);
        $page->nl($pointer);

        if (!$this->getDi()->config->get('invoice_do_not_include_terms')) {
            $termsText = new Am_TermsText($invoice);
            $page->drawTextWithFixedWidth(___('Subscription Terms') . ': ' . $termsText, $left, $pointer, $this->getPaperWidth() - 2 * $padd);
            $page->nl($pointer);
        }

        $p = new stdClass();
        $p->value = & $pointer;

        $this->getDi()->hook->call(Am_Event::PDF_INVOICE_AFTER_TABLE, array(
            'page' => $page,
            'pointer' => $p,
            'invoice' => $invoice,
            'payment' => $payment,
            'user' => $user
        ));

        if (!$this->getDi()->config->get('invoice_custom_template') ||
            !$this->getDi()->uploadTable->load($this->getDi()->config->get('invoice_custom_template')))
        {
            if ($ifn = $this->getDi()->config->get('invoice_footer_note'))
            {
                $page->nl($pointer);
                $page->drawTextWithFixedWidth($ifn, $left, $pointer, $this->getPaperWidth() - 2 * $padd);
            }
        }
        return $pdf->render();
    }

    protected function renderAccess($page, $pointer)
    {
        $invoice = $this->invoice;
        //if user is not approved there is no access records
        $accessrecords = $invoice->getAccessRecords();
        if(!$accessrecords) return $pointer;
        $payment = $this->payment;

        $padd = 20;
        $width_date = 120;

        $fontH = $this->getFontRegular();
        $fontHB = $this->getFontBold();


        $table = new Am_Pdf_Table();
        $table->setMargin($padd, $padd, $padd, $padd);
        $table->setStyleForRow(
            1, array(
            'shape' => array(
                'type' => Zend_Pdf_Page::SHAPE_DRAW_FILL_AND_STROKE,
                'color' => new Zend_Pdf_Color_Html("#cccccc")
            ),
            'font' => array(
                'face' => $fontHB,
                'size' => 10
            )
            )
        );

        $table->setStyleForColumn( //from
            2, array(
            'width' => $width_date
            )
        );
        $table->setStyleForColumn( //to
            3, array(
            'width' => $width_date
            )
        );

        $table->addRow(array(
            ___('Subscription/Product Title'),
            ___('From'),
            ___('To')
        ));
        
        $get_product_ids = create_function('$access', 'return $access->product_id;');
        $productOptions = $this->getDi()->productTable->getProductTitles(array_map($get_product_ids, $accessrecords));

        foreach ($accessrecords as $a)
        {

            /* @var $a Access */
            if ($a->invoice_payment_id != $payment->pk()) continue;
            $table->addRow(array(
                $productOptions[$a->product_id],
                amDate($a->begin_date),
                $a->expire_date == Am_Period::MAX_SQL_DATE ? ___('Lifetime') : amDate($a->expire_date)
            ));
        }

        $pointer = $page->drawTable($table, 0, $pointer);


        return $pointer;
    }

    protected function getState(Invoice $invoice)
    {
        $state = $this->getDi()->stateTable->findFirstBy(array(
                'state' => $invoice->getState()
            ));
        return $state ? $state->title : $invoice->getState();
    }

    protected function getCountry(Invoice $invoice)
    {
        $country = $this->getDi()->countryTable->findFirstBy(array(
                'country' => $invoice->getCountry()
            ));
        return $country ? $country->title : $invoice->getCountry();
    }

}

