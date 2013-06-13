<?php

abstract class TranslationDataSource_Abstract {
    const FETCH_MODE_ALL = 'all';
    const FETCH_MODE_REWRITTEN = 'rewritten';
    const FETCH_MODE_UNTRANSLATED = 'untranslated';
    const LOCALE_KEY = '_default_locale';

    public function getTranslationData($language, $fetchMode = TranslationDataSource_Abstract::FETCH_MODE_ALL, &$locale = null) {
        switch ($fetchMode) {
            case self::FETCH_MODE_ALL :
                $result = $this->getBaseTranslationData($language);
                $result = $this->mergeWithCustomTranslation($result, $language);
                break;
            case self::FETCH_MODE_REWRITTEN :
                $result = Am_Di::getInstance()->translationTable->getTranslationData($language);
                $base = $this->getBaseTranslationData($language);
                foreach ($result as $k=>$v) {
                    if (!key_exists($k, $base)) {
                        unset($result[$k]);
                    }
                }
                break;
            case self::FETCH_MODE_UNTRANSLATED :
                $result = $this->getBaseTranslationData($language);
                $result = $this->mergeWithCustomTranslation($result, $language);
                $result = array_filter($result, create_function('$v', 'return (boolean)!$v;'));
                break;
            default:
                throw new Am_Exception_InternalError('Unknown fetch mode : ' . $fetchMode);
                break;
        }

        if (isset($result[self::LOCALE_KEY])) {
            $locale = $result[self::LOCALE_KEY];
            unset($result[self::LOCALE_KEY]);
        }
        return $result;
    }

    public function createTranslation($language) {
        $filename = $this->getFileName($language);
        $path = ROOT_DIR . "/application/default/language/user/{$filename}";

        if ($error = $this->validatePath($path)) {
            return $error;
        }

        $content = $this->getFileContent($language);
        file_put_contents($path, $content);
        return '';
    }

    private function mergeWithCustomTranslation($translationData, $language) {
        $custom = Am_Di::getInstance()->translationTable->getTranslationData($language);       
        foreach ($translationData as $k=>$v) {
            if (isset($custom[$k])) {
                $translationData[$k] = $custom[$k];
            }
        }
        return $translationData;
    }

    private function getBaseTranslationData($language) {
        return $this->_getBaseTranslationData($language);
    }

    private function validatePath($path) {
        if (file_exists($path)) {
            return "File $path already exist. You can not create already existing translation.";
        }

        $dir = dirname($path);
        if (!is_writeable($dir)) {
            return "Folder $dir is not a writable for the PHP script. Please <br />
            chmod this file using webhosting control panel file manager or using your<br />
            favorite FTP client to 666 (write and read for all)<br />
            After creation of translation, please don't forget to chmod it back to 644.
                    ";
        }

        return '';
    }


    abstract protected function _getBaseTranslationData($language);
    abstract function getFileName($language);
    abstract function getFileContent($language, $translationData = array());
}

class TranslationDataSource_PHP extends TranslationDataSource_Abstract {
    function getFileName($language) {
        return $language . '.php';
    }

    function getFileContent($language, $translationData = array()) {
        $expectedLocaleName = $language;
        $locale = new Zend_Locale($expectedLocaleName);
        //prepend local to start of array
        $translationData = array_reverse($translationData);
        $translationData[self::LOCALE_KEY] = $locale;
        $translationData = array_reverse($translationData);

        $out = '';
        $out .= "<?php"
                . PHP_EOL
                . "return array ("
                . PHP_EOL;

        foreach ($translationData as $msgid => $msgstr) {
            $out .= "\t";
            $out .= sprintf("'%s'=>'%s',",
            str_replace("'", "\'", $msgid),
            str_replace("'", "\'", $msgstr)
            );
            $out .= PHP_EOL;
        }
        $out .= "\t''=>''" . PHP_EOL;
        $out .= ");";
        return $out;
    }

    protected function _getBaseTranslationData($language) {
        $result = include(APPLICATION_PATH . "/default/language/user/default.php");
        $result = array_merge($result, (array)@include(APPLICATION_PATH . "/default/language/user/{$language}.php"));
        $result = array_merge($result, (array)@include(APPLICATION_PATH . "/default/language/admin/default.php"));
        $result = array_merge($result, (array)@include(APPLICATION_PATH . "/default/language/admin/{$language}.php"));
        return $result;
    }
}

class TranslationDataSource_PO extends TranslationDataSource_Abstract {


    function getFileName($language) {
        return $language . '.po';
    }

    function getFileContent($language, $translationData=array()) {
        $expectedLocaleName = $language;
        $locale = new Zend_Locale($expectedLocaleName);
        //prepend local to start of array
        $translationData = array_reverse($translationData);
        $translationData[self::LOCALE_KEY] = $locale;
        $translationData = array_reverse($translationData);

        $out = '';

        foreach ($translationData as $msgid => $msgstr) {
            $out .= sprintf('msgid "%s"', $this->prepare($msgid, true));
            $out .= PHP_EOL;
            $out .= sprintf('msgstr "%s"', $this->prepare($msgstr, true));
            $out .= PHP_EOL;
            $out .= PHP_EOL;
        }

        return $out;
    }

    protected function _getBaseTranslationData($language) {
        $result= array();
        $result = $this->getTranslationArray(APPLICATION_PATH . "/default/language/user/default.pot");
        $result = array_merge($result, $this->getTranslationArray(APPLICATION_PATH . "/default/language/user/{$language}.po"));
        return $result;
    }

    private function getTranslationArray($file) {
        $result = array();

        $fPointer = fopen($file, 'r');

        $part = '';
        while (!feof($fPointer)) {
            $line = fgets($fPointer);
            $part .= $line;
            if (!trim($line)) { //entity divided with empty line in file
                $result = array_merge($result, $this->getTranslationEntity($part));
                $part = '';
            }
        }

        fclose($fPointer);

        unset($result['']);//unset meta
        return $result;
    }

    private function getTranslationEntity($contents) {
        $result = array();
        $matches = array();

        $matched = preg_match(
                '/(msgid\s+("([^"]|\\\\")*?"\s*)+)\s+' .
                '(msgstr\s+("([^"]|\\\\")*?"\s*)+)/u',
                $contents, $matches
        );

        if ($matched) {
            $msgid = $matches[1];
            $msgid = preg_replace(
                    '/\s*msgid\s*"(.*)"\s*/s', '\\1', $matches[1]);
            $msgstr = $matches[4];
            $msgstr = preg_replace(
                    '/\s*msgstr\s*"(.*)"\s*/s', '\\1', $matches[4]);
            $result[$this->prepare($msgid)] = $this->prepare($msgstr);

        }

        return $result;

    }

    private function prepare($string, $reverse = false) {
        if ($reverse) {
            $smap = array('"', "\n", "\t", "\r");
            $rmap = array('\\"', '\\n"' . "\n" . '"', '\\t', '\\r');
            return (string) str_replace($smap, $rmap, $string);
        } else {
            $smap = array('/"\s+"/', '/\\\\n/', '/\\\\r/', '/\\\\t/', '/\\\\"/');
            $rmap = array('', "\n", "\r", "\t", '"');
            return (string) preg_replace($smap, $rmap, $string);
        }
    }
}

class TranslationDataSource_DB extends TranslationDataSource_Abstract {

    public function createTranslation($language) {
        throw new Am_Exception_InputError('Local translations can not be created');
    }

    function getFileName($language) {
        throw new Am_Exception_InputError('Local translations can not be exported');
    }

    function getFileContent($language, $translationData = array()) {
        throw new Am_Exception_InputError('Local translations can not be exported');
    }

    protected function _getBaseTranslationData($language) {
        $result = array();
        foreach (Am_Di::getInstance()->db->select("SELECT title, description FROM ?_product") as $r) {
            $result[ $r['title'] ] = "";
            $result[ $r['description'] ] = "";
        }
        foreach (Am_Di::getInstance()->db->select("SELECT terms FROM ?_billing_plan WHERE terms<>''") as $r) {
            $result[ $r['terms'] ] = "";
        }
        foreach ((array)Am_Di::getInstance()->config->get('member_fields') as $field) {
            $result[ $field['title'] ] = "";
            $result[ $field['description'] ] = "";
        }
        return $result;
    }
}

class Am_Grid_DataSource_Array_Trans extends Am_Grid_DataSource_Array {
    /* @var TranslationDataProvider_Abstract */
    protected $tDataSource = null;
    protected $language = null;

    public function __construct($language) {
        $this->tDataSource = $this->createTDataSource();
        $this->language = $language;

        $translationData = $this->tDataSource->getTranslationData(
                $this->language, TranslationDataSource_Abstract::FETCH_MODE_ALL
        );
        return parent::__construct(self::prepareArray($translationData));
    }

    public function getLanguage() {
        return $this->language;
    }

    public static function prepareArray($translationData) {
        $records = array();
        foreach ($translationData as $base => $trans) {
            $record = new stdClass();
            $record->base = $base;
            $record->trans = $trans;
            $records[] = $record;
        }
        return $records;
    }

    /**
     * @return TranslationDataSource_Abstract
     */
    public function getTDataSource() {
        return $this->tDataSource;
    }

    protected function createTDataSource() {
        return new TranslationDataSource_PHP();
    }
}

class Am_Grid_Action_NewTrans extends Am_Grid_Action_Abstract {
    protected $title = "Create New";
    protected $type = self::NORECORD; // this action does not operate on existing records
    public function run() {
        $form = $this->getForm();
        if (!$form->isSubmitted()) {
            // nop
        } elseif ($form->validate()) {
            $error = $this->grid->getDataSource()
                    ->getTDataSource()
                    ->createTranslation(
                    $this->grid->getCompleteRequest()->getParam('new_language')
            );
            if ($error) {
                $form->setError($error);
            } else {
                Zend_Locale::hasCache() && Zend_Locale::clearCache();
                Zend_Translate::hasCache() && Zend_Translate::clearCache();

                Am_Di::getInstance()->cache->clean();
                $this->grid->redirectBack();
            }
        }
        echo $this->renderTitle();
        echo $form;
    }

    public function getForm() {
        $languageTranslation = Am_Locale::getSelfNames();
        
        $avalableLocaleList = Zend_Locale::getLocaleList();
        $existingLanguages = Am_Di::getInstance()->languagesListUser;
        $languageOptions = array();

        foreach ($avalableLocaleList as $k=>$v) {
            $locale = new Zend_Locale($k);
            if (!array_key_exists($locale->getLanguage(), $existingLanguages) &&
                    isset($languageTranslation[$locale->getLanguage()])) {

                $languageOptions[$locale->getLanguage()] = "($k) " . $languageTranslation[$locale->getLanguage()];
            }
        }

        asort($languageOptions);

        $form = new Am_Form_Admin();
        $form->setAction($this->grid->makeUrl(null));

        $form->addElement('select', 'new_language')
                ->setLabel('Language')
                ->loadOptions($languageOptions)
                ->setId('languageSelect');
        $form->addElement('hidden', 'a')
                ->setValue('new');

        $form->addSaveButton();

        foreach ($this->grid->getVariablesList() as $k) {
            if ($val = $this->grid->getRequest()->get($k)) {
                $form->addHidden($this->grid->getId() .'_'. $k)->setValue($val);
            }
        }

        return $form;
    }
}

class Am_Grid_Action_ExportTrans extends Am_Grid_Action_Abstract {
    protected $title = "Export";
    protected $type = self::NORECORD; // this action does not operate on existing records
    public function run() {

        if (! $language = $this->grid->getCompleteRequest()->get('language')) {
            $language = Am_Di::getInstance()->locale->getLanguage();
        }

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest'; //return response without layout
        $outputDataSource = new TranslationDataSource_PO();
        $inputDataSource = $this->grid->getDataSource()->getTDataSource();
        
        $filename = $outputDataSource->getFileName($language);

        Am_Controller::noCache();
        header('Content-type: text/plain');
        header("Content-Disposition: attachment; filename=$filename");
        echo $outputDataSource->getFileContent($language, $inputDataSource->getTranslationData($language, TranslationDataSource_Abstract::FETCH_MODE_REWRITTEN));
    }
}
class Am_Grid_Filter_Trans extends Am_Grid_Filter_Abstract {

    protected $language = null;

    protected $varList = array(
            'filter', 'mode'
    );

    public function  __construct($lang) {
        $this->language = $lang;
    }

    public function setLanguage($lang) {
        $this->language = $lang;
    }

    protected function getLanguage() {
        return $this->language;
    }

    protected function applyFilter() {
        $tDataSource = $this->grid->getDataSource()->getTDataSource();

        $translationData = $tDataSource->getTranslationData(
                $this->getLanguage(),
                $this->getParam('mode', TranslationDataSource_Abstract::FETCH_MODE_ALL)
        );
        $translationData = $this->filter($translationData,
                $this->getParam('filter'));
        $this->grid->getDataSource()->_friendSetArray(
                Am_Grid_DataSource_Array_Trans::prepareArray($translationData)
        );
    }

    function renderInputs() {

        $options = array(
                TranslationDataSource_Abstract::FETCH_MODE_ALL => 'All',
                TranslationDataSource_Abstract::FETCH_MODE_REWRITTEN => 'Customized Only',
                TranslationDataSource_Abstract::FETCH_MODE_UNTRANSLATED => 'Untranslated Only'
        );

        $filter = ___('Display Mode').' ';

        $filter .= $this->renderInputSelect('mode', $options, array('id'=>'trans-mode'));
        $filter .= ' '.___('Filter by String').' ';
        $filter .= $this->renderInputText('filter');
        $filter .= sprintf('<input type="hidden" name="language" value="%s">', $this->getLanguage());

        return $filter;
    }

    protected function filter($array, $filter) {
        if (!$filter) return $array;
        foreach ($array as $k=>$v) {
            if (false===stripos($k, $filter) &&
                    false===stripos($v, $filter)) {

                unset($array[$k]);
            }
        }
        return $array;
    }
}



class AdminTransGlobalController extends Am_Controller_Grid {
    protected $language = null;

    public function checkAdminPermissions(Admin $admin) {
        return $admin->isSuper();
    }
    public function init() {

        $this->getView()->headScript()->appendScript(
                $this->getJs()
        );

        if ($language = $this->_request->get('language')) {
            $this->language = $language;
        } else {
            $locale = new Zend_Locale(Zend_Registry::get('Zend_Translate')->getLocale());
            $this->language = $locale->getLanguage();
        }

        parent::init();
    }

    public function createGrid() {
        $grid = $this->_createGrid('Global Translations');
        $grid->actionAdd(new Am_Grid_Action_NewTrans);
        $actionExport = new Am_Grid_Action_ExportTrans();
        $actionExport->setTarget('_top');
        $grid->actionAdd($actionExport);
        return $grid;
    }

    protected function _createGrid($title) {
        $ds = $this->createDS($this->getLanguage());
        $grid = new Am_Grid_Editable('_trans', $title, $ds, $this->_request, $this->view);
        $grid->addField(new Am_Grid_Field('base', 'Base', true, '', null, '50%'));
        $grid->addField(new Am_Grid_Field('trans', 'Translation', true, '', array($this, 'renderTrans'), '50%'));
        $grid->setFilter(new Am_Grid_Filter_Trans($this->getLanguage()));
        $grid->actionsClear();
        $grid->setRecordTitle('Translation');
        $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_CONTENT, array($this, 'wrapContent'));
        $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_TABLE, array($this, 'wrapTable'));
        return $grid;
    }

    protected function createDS($language) {
        return new Am_Grid_DataSource_Array_Trans($language);
    }

    function wrapTable(& $out, $grid) {
        $out = '<form method="post" target="_top" name="translations" action="'
                . $this->getUrl(null, 'save', null, array('language'=>$this->getLanguage()))
                . '">'
                . $out
                . sprintf('<input type="hidden" name="language" value="%s">', $this->getLanguage());

        $vars = $this->grid->getVariablesList();
        $vars[] = 'p'; //stay on current page
        foreach ($vars as $var) {
            if ($val = $this->grid->getRequest()->getParam($var)) {
                $out .= sprintf('<input type="hidden" name="%s" value="%s">', $this->grid->getId() . '_' . $var, $val);
            }
        }
        $out .= '<div class="group-wrap"><input type="submit" name="submit" value="Save"></div>'
                . '</form>';
    }

    function wrapContent(& $out, $grid) {
        $out = $this->renderLanguageSelection()
                . $out;
    }

    function renderTrans($record) {
        return $this->renderTd(sprintf('<textarea class="text-edit" name="trans[%s]">%s</textarea>',
                base64_encode($record->base), Am_Controller::escape($record->trans)), false);
    }

    public function getLanguage() {
        return $this->language;
    }

    function saveAction() {
        $trans = $this->getRequest()->getParam('trans', array());
        $translationData = $this->grid->getDataSource()
                ->getTDataSource()
                ->getTranslationData(
                $this->getLanguage(), TranslationDataSource_Abstract::FETCH_MODE_ALL
        );

        $toReplace = array();
        foreach ($trans as $k=>$v) {
            $k = base64_decode($k);
            if ( $v != $translationData[$k] ) {
                $toReplace[$k] = $v;
            }
        }

        if (count($toReplace)) {
            $this->getDi()->translationTable->replaceTranslation($toReplace, $this->language);
            if (Zend_Translate::hasCache()) {
                Zend_Translate::clearCache();
            }
        }

        $_POST['trans'] = $_GET['trans'] = null;
        $this->grid->getRequest()->setParam('trans', null);
        $this->grid->getCompleteRequest()->setParam('trans', null);
        $this->getRequest()->setParam('trans', null);

        $url = $this->makeUrl(null, 'index', null, $this->getRequest()->toArray());
        
        $this->isAjax() ?
                $this->redirectAjax($url, 'Redirect') :
                $this->redirectLocation($url);
    }

    protected function renderLanguageSelection() {
        $form = new Am_Form_Admin();

        $form->addElement('select', 'language')
                ->setLabel('Language')
                ->setValue($this->getLanguage())
                ->loadOptions($this->getLanguageOptions());

        $renderer = HTML_QuickForm2_Renderer::factory('array');

        $form->render($renderer);

        $form = $renderer->toArray();
        $filter = '';
        foreach ($form['elements'] as $el) {
            $filter .= ' ' . $el['label'] . ' ' . $el['html'];
        }
        return sprintf("<div class='filter-wrap'><form class='filter' method='get' action='%s'>\n",
                $this->escape($this->getUrl(null, 'index'))) .
                $filter .
                "</form></div>\n" ;

    }

    protected function getLanguageOptions() {
        return $this->getDi()->languagesListUser;
    }

    protected function getJs() {

        $revertIcon = $this->getView()->icon('revert');

        $jsScript = <<<CUT
(function($){
    $(function() {
        $('form.filter select#trans-mode').live('change', function() {
            $(this).parents('form').get(0).submit();
        })
    })

    var changedNum = 0;
    $(".text-edit").live('focus', function(event) {
        if (!$(this).data('valueSaved')) {
            $(this).data('valueSaved', true);
            $(this).data('value', $(this).attr('value'));
        }
    })

    $("select[name=language]").live('change', function(){
        this.form.submit();
    });

    $(".text-edit").live('change', function(event) {
        if (!$(this).hasClass('changed')) {
            $(this).addClass('changed');
            var aRevert = $('<a href="#">{$revertIcon}</a>').attr('title', $(this).data('value')).click(function(){
                input = $(this).prev();
                input.attr('value', input.data('value'));
                $(this).remove();
                input.removeClass('changed');
                changedNum--;
                if (!changedNum && $(".pagination").hasClass('hidden')) {
                    $(".pagination").next().remove();
                    $(".pagination").removeClass('hidden');
                    $(".pagination").show();
                }
                return false;
            })
            changedNum++;
            $(this).after(aRevert);
            aRevert.before(' ');
        }
        var aCancel = $('<a href="#">Cancel All Changes in Translations on Current Page</a>').click(function(){
            $(".text-edit").filter(".changed").each(function(){
                 input = $(this);
                 input.attr('value', input.data('value'));
                 input.next().remove();
                 input.removeClass('changed');
             })
             if ($(".pagination").hasClass('hidden')) {
                 $(".pagination").next().remove();
                 $(".pagination").removeClass('hidden');
                 $(".pagination").show();
             }
             changedNum = 0;
             return false;
        })

        aCancel = aCancel.wrap('<div class="trans-cancel"></div>').parents('div');

        if ($(".pagination").css('display')!='none') {
            $(".pagination").addClass('hidden')
            $(".pagination").after(aCancel);
            $(".pagination").hide();
        }
    })
})(jQuery)
CUT;
        return $jsScript;
    }
}