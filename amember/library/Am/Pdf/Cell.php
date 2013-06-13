<?php

/**
 * @package Am_Pdf  
 */
class Am_Pdf_Cell {
    protected $left;
    protected $top;
    protected $width;
    protected $content;
    protected $align;
    protected $styles;
    const TOP = 1;
    const RIGHT = 2;
    const BOTTOM = 3;
    const LEFT = 4;
    protected $padding = array(
            self::TOP => 0,
            self::RIGHT => 0,
            self::BOTTOM => 0,
            self::LEFT => 0
    );

    public function setStyle($styles) {
        $this->styles = $styles;
    }

    public function __construct($content) {
        $this->content = $content;
    }

    public function setWidth($width) {
        $this->width = $width;
    }

    public function setPadding($top=0, $right=0, $bottom=0, $left=0) {
        $this->padding = array(
                self::TOP => $top,
                self::RIGHT => $right,
                self::BOTTOM => $bottom,
                self::LEFT => $left
        );
    }

    public function getPadding($side) {
        return $this->padding[$side];
    }

    public function getWidth() {
        return $this->width;
    }

    protected function drawBorder(Am_Pdf_Page_Decorator $page, $x, $y) {
        $rowHeight = $page->getFont()->getLineHeight()/100;

        $shape = $this->getProperty('shape',
                array(
                'type' => Zend_Pdf_Page::SHAPE_DRAW_STROKE,
                'color' => new Zend_Pdf_Color_Html('#cccccc')
        ));


        $page->setLineColor($shape['color']);
        $page->setFillColor($shape['color']);

        $page->drawRectangle(
                $x, $y,
                $x + $this->getWidth(),
                $y + $rowHeight + $this->getPadding(self::BOTTOM) +
                $this->getPadding(self::TOP), $shape['type']);

        $page->setFillColor(new Zend_Pdf_Color_Html('#000000'));
    }

    public function render(Am_Pdf_Page_Decorator $page, $x, $y) {
        $this->drawBorder($page, $x, $y);

        if ($font = $this->getProperty('font')) {
            $fontTmp = $page->getFont();
            $fontSizeTmp = $page->getFontSize();

            $page->setFont($font['face'], $font['size']);
        }

        $content = $this->content;
        $width = $page->widthForString($content);
        if ($width > $this->getWidth()) {
            $cnt = floor(mb_strlen($content) * $this->getWidth() / $width) - 4;
            $content = mb_substr($content, 0, $cnt) . '...';
        }

        switch ($this->getProperty('align', Am_Pdf_Page_Decorator::ALIGN_LEFT)) {
            case Am_Pdf_Page_Decorator::ALIGN_LEFT :
                $page->drawText($content,
                        $x + 1 + $this->getPadding(self::LEFT),
                        $y + $this->getPadding(self::BOTTOM));
                break;
            case Am_Pdf_Page_Decorator::ALIGN_RIGHT :
                $page->drawText($content,
                        $x + $this->getWidth() - 1 - $this->getPadding(self::RIGHT),
                        $y + $this->getPadding(self::BOTTOM), 'UTF-8', Am_Pdf_Page_Decorator::ALIGN_RIGHT);
                break;
        }

        if ($font) {
            $page->setFont($fontTmp, $fontSizeTmp);
        }
    }

    protected function getProperty($propName, $default = null) {
        If (isset($this->styles[$propName])) {
            return $this->styles[$propName];
        } else {
            return $default;
        }
    }

    public function getHeight($page) {
        return $page->getFont()->getLineHeight()/100 +
                $this->getPadding(self::BOTTOM) +
                $this->getPadding(self::TOP);
    }

}

