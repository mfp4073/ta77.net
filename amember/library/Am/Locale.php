<?php

/**
 * Provides locale-based information and guess client locale
 * requires <i>locales.dat</i> file to be located in the same folder
 * 
 * @package Am_Utils
 */
class Am_Locale 
{
    protected $locale = '';
    protected static $cache = array();
    
    static protected $localeAliases = array(
        'af' => array(
             'af_NA',
             'af_ZA',
        ),
        'ar' =>  array(
             'ar_AE',
             'ar_BH',
             'ar_DZ',
             'ar_EG',
             'ar_IQ',
             'ar_JO',
             'ar_KW',
             'ar_LB',
             'ar_LY',
             'ar_MA',
             'ar_OM',
             'ar_QA',
             'ar_SA',
             'ar_SD',
             'ar_SY',
             'ar_TN',
             'ar_YE',
        ),
        'de' =>
        array(
             'de_AT',
             'de_BE',
             'de_CH',
             'de_DE',
             'de_LI',
             'de_LU',
        ),
        'en' =>
        array(
             'en_AS',
             'en_AU',
             'en_BB',
             'en_BE',
             'en_BM',
             'en_BW',
             'en_BZ',
             'en_CA',
             'en_GB',
             'en_GU',
             'en_GY',
             'en_HK',
             'en_IE',
             'en_IN',
             'en_JM',
             'en_MH',
             'en_MP',
             'en_MT',
             'en_MU',
             'en_NA',
             'en_NZ',
             'en_PH',
             'en_PK',
             'en_SG',
             'en_TT',
             'en_UM',
             'en_US',
             'en_VI',
             'en_ZA',
             'en_ZW',
        ),
        'es' =>
        array(
             'es_419',
             'es_AR',
             'es_BO',
             'es_CL',
             'es_CO',
             'es_CR',
             'es_DO',
             'es_EC',
             'es_ES',
             'es_GQ',
             'es_GT',
             'es_HN',
             'es_MX',
             'es_NI',
             'es_PA',
             'es_PE',
             'es_PR',
             'es_PY',
             'es_SV',
             'es_US',
             'es_UY',
             'es_VE',
        ),
        'fr' =>
        array(
             'fr_BE',
             'fr_BF',
             'fr_BI',
             'fr_BJ',
             'fr_BL',
             'fr_CA',
             'fr_CD',
             'fr_CF',
             'fr_CG',
             'fr_CH',
             'fr_CI',
             'fr_CM',
             'fr_DJ',
             'fr_FR',
             'fr_GA',
             'fr_GF',
             'fr_GN',
             'fr_GP',
             'fr_GQ',
             'fr_KM',
             'fr_LU',
             'fr_MC',
             'fr_MF',
             'fr_MG',
             'fr_ML',
             'fr_MQ',
             'fr_NE',
             'fr_RE',
             'fr_RW',
             'fr_SN',
             'fr_TD',
             'fr_TG',
             'fr_YT',
        ),
        'pt' =>
        array(
             'pt_AO',
             'pt_BR',
             'pt_GW',
             'pt_MZ',
             'pt_PT',
             'pt_ST',
        ),
        'zh' =>
        array(
             'zh_Hans',
             'zh_Hans_CN',
             'zh_Hans_HK',
             'zh_Hans_MO',
             'zh_Hans_SG',
             'zh_Hant',
             'zh_Hant_HK',
             'zh_Hant_MO',
             'zh_Hant_TW',
        ),
    );

    public function __construct($locale = null)
    {
        if ($locale === null)
            $locale = key(Zend_Locale::getDefault ());
        elseif (empty($locale))
            $locale = 'en_US';
        $this->locale = $locale;
    }
    
    function getId()
    {
        return $this->locale;
    }
    
    public function getTerritoryNames()
    {
        $data = $this->getData();
        return (array)@$data['territories'];
    }
    public function getDateFormat()
    {
        $data = $this->getData();
        return empty($data['dateFormats']['php']) ? 'M d, Y' : $data['dateFormats']['php'];
    }
    public function getTimeFormat()
    {
        $data = $this->getData();
        return empty($data['timeFormats']['php']) ? 'H:i:s' : $data['timeFormats']['php'];
    }
    public function getDateTimeFormat()
    {
        $data = $this->getData();
        return strtr($data['dateTimeFormat'], array(
            '{1}' => $this->getDateFormat(),
            '{0}' => $this->getTimeFormat(),
        ));
    }
    
    protected function getData()
    {
        if (isset(self::$cache[$this->locale]))
            return self::$cache[$this->locale];
        $data = Am_Di::getInstance()->cacheFunction->call(array('Am_Locale','_readData'), array($this->locale));
        return self::$cache[$this->locale] = $data;
    }
    
    public function getMonthNames($type = 'wide', $standalone = true)
    {
        $data = $this->getData();
        if ($standalone) $type = 'narrow';
        return $data[$standalone ? 'monthNamesSA' : 'monthNames'][$type];
    }
    
    public function getWeekdayNames($type = 'wide')
    {
        $data = $this->getData();
        return (array)$data['weekDayNames'][$type];
    }
    
    /**
     * @param string $locale
     * @return array data
     */
    static function _readData($locale)
    {
        $readLocales = array();
        $arr = explode('_', $locale);
        do {
            $readLocales[] = implode('_', $arr);
            array_pop($arr);
        } while ($arr);
        $readLocales = array_reverse($readLocales);
        if ($locale != 'selfNames')
            array_unshift($readLocales, 'root');
        
        $f = fopen(dirname(__FILE__).'/locale.dat', 'r');
        if (!$f)
            throw new Am_Exception_InternalError("Could not open locale data file: locales.dat");
        /*
         * stream_get_line does not return correct result in php 5.2.6
         */
        if(version_compare(PHP_VERSION, '5.2.7', '<'))
            list($line, ) = explode(chr(5), file_get_contents(dirname(__FILE__).'/locale.dat', false, NULL, -1, 32000));
        else
            $line = stream_get_line($f, 64000, chr(5));
        
        $header = unserialize(substr($line, strlen('LOCALES:')));
        // now read
        $data = array();
        foreach ($readLocales as $locale)
        {
            $start = $header[$locale][0];
            $len   = $header[$locale][1] - $header[$locale][0];
            fseek($f, strlen($line) + $start + 1);
            $string = fread($f, $len);
            $data = array_merge($data, (array)unserialize($string));
        }
        return $data;
    }
    
    static function getSelfNames()
    {
        return self::_readData('selfNames');
    }
    
    static function getLocales()
    {
        $f = fopen(dirname(__FILE__).'/locale.dat', 'r');
        if (!$f)
            throw new Am_Exception_InternalError("Could not open locale data file: locales.dat");
        $line = stream_get_line($f, 64000, chr(5));
        $header = unserialize(substr($line, strlen('LOCALES:')));
        unset($header['root']);
        return array_keys($header);
    }
    static function getLanguagesList($context = 'user')
    {
        $options = array();
        $selfNames = (array)self::getSelfNames();
        foreach (glob(APPLICATION_PATH.'/default/language/'.$context.'/*.php') as $fn)
        {
            if (!preg_match('|\b([a-z]{2,3}(_[A-Za-z0-9]+)?)\.php$|', $fn, $regs)) continue;
            $lang = $regs[1];
            $langs = self::getLocaleAliases($lang);
            array_unshift($langs, $lang);
            foreach ($langs as $lang)
            {
                $title = mb_convert_case(@$selfNames[$lang], MB_CASE_TITLE, 'UTF-8');
                $options[$lang] = $title ? $title : $lang;
            }
        }
        return $options;
    }
    /**
     * Return locales list of same language but with different locale settings
     * That way we do not have to create additional files in languages/ folder
     * @return array of string
     */
    static function getLocaleAliases($locale)
    {
        return array_key_exists($locale, self::$localeAliases) ? self::$localeAliases[$locale] : array();
    }
    /**
     * Find out locale from the request, settings or session
     * if language choice enabled, try the following:
     *      - REQUEST parameter "lang"
     *      - SESSION parameter "lang"
     *      - Am_App::getUser->lang
     *      - default in App
     *      - en_US
     * else use latter 2
     */
    static function initLocale(Am_Di $di)
    {
        if (defined('AM_ADMIN') && AM_ADMIN)
        {
            Zend_Locale::setDefault('en_US');
        } else {
            $possibleLang = array();
            if ($di->config->get('lang.display_choice'))
            {
                $auth = $di->auth;
                $user = $auth->getUserId() ? $auth->getUser() : null;
                if (!empty($_REQUEST['_lang']))
                    $possibleLang[] = filterId($_REQUEST['_lang']);
                elseif (!empty($di->session->lang))
                    $possibleLang[] = $di->session->lang;
                elseif ($user && $user->lang) 
                    $possibleLang[] = $user->lang;
                $br = Zend_Locale::getBrowser();
                arsort($br);
                $possibleLang = array_merge($possibleLang, array_keys($br));
            }    
                
            $possibleLang[] = $di->config->get('lang.default', 'en_US');
            $possibleLang[] = 'en_US'; // last but not least
            // now choose the best candidate
            $enabledLangs = $di->config->get('lang.enabled', array());
            $checked = array();
            foreach ($possibleLang as $lc)
            {
                list($lang) = explode('_', $lc, 2);
                if (!in_array($lc, $enabledLangs) && !in_array($lang, $enabledLangs)) continue;
                if ($lc == $lang) 
                { // we have not got entire locale,guess it
                    if ($lc == 'en')
                        $lc = 'en_US';
                    elseif ($lc == 'sv') 
                        $lc = 'sv_SE';
                    elseif ($lc == 'et')
                        $lc = 'et_EE';
                    else
                        $lc = Zend_Locale::getLocaleToTerritory($lang);
                    if (!$lc && $lang == 'ja')
                        $lc = 'ja_JP';
                    if (!$lc && $lang == 'nb')
                        $lc = 'nb_NO';
                    if (!$lc && $lang == 'zh')
                        $lc = 'zh_Hans';
                    if (!$lc && $lang == 'el')
                        $lc = 'el_GR';
                    if (!$lc && $lang == 'he')
                        $lc = 'he_IL';
                    if (!$lc && $lang == 'da')
                        $lc = 'da_DK';
                    if (!$lc) continue;
                }
                if (isset($checked[$lc])) continue;
                $checked[$lc] = true;
                // check if locale file is exists
                $lc = preg_replace('/[^A-Za-z0-9_]/', '', $lc);
                if (!Zend_Locale::isLocale($lc)) continue;
                Zend_Locale::setDefault($lc);
                // then update user if it was request
                // and set to session
                break;
            }
            if($di->config->get('lang.display_choice') && !empty($_REQUEST['_lang']))
            {
                if ((($_REQUEST['_lang'] == $lang) || ($_REQUEST['_lang'] == $lc)) && $user && $user->lang != $lang)
                    $user->updateQuick('lang', $lc);
                // set to session
                $di->session->lang = $lc;
            }
        }
        Zend_Registry::set('Zend_Locale', new Zend_Locale());
        $amLocale = new Am_Locale();
        Zend_Registry::set('Am_Locale', $amLocale);
        $di->locale = $amLocale;
        Zend_Locale_Format::setOptions(array(
            'date_format' => $amLocale->getDateFormat(),
        ));
    }
    
    /**
     * Returns the language part of the locale
     *
     * @return string
     */
    public function getLanguage()
    {
        $locale = explode('_', $this->locale);
        return $locale[0];
    }
    
    
    public function formatPeriod(Am_Period $period, $format="%s", $skip_one_c = false)
    {
        switch ($period->getUnit()){
            case 'd': $uu = $period->getCount()==1 ? ___('day'): ___('days'); break;
            case 'm': $uu = $period->getCount()==1 ? ___('month') : ___('months'); break;
            case 'y': $uu = $period->getCount()==1 ? ___('year') : ___('years'); break;
            case Am_Period::FIXED: 
                if ($period->getCount() == Am_Period::MAX_SQL_DATE)
                    return " for lifetime";
                return " up to " . amDate($period->getCount());
        }
        $cc = $period->getCount();
        if ($period->getCount() == 1) $cc = $skip_one_c ? '' : 'one';
        return sprintf($format, "$cc $uu");
    }
    
    /**
     * Possible unit values;
     *  day,day-future,day-past,hour,hour-future,hour-past,minute,minute-future,minute-past,month,month-future,month-past,second,second-future,second-past,week,week-future,week-past,year,year-future,year-past
     * @param string $unit 
     * @param unit $count number of units to format
     * @return string
     */
    public function formatUnits($unit, $count)
    {
        $data = $this->getData();
        $alternatives = $data['units']['day'];
        $pl = $this->findPlural($count);
        return str_replace('{0}', $count, $alternatives[$pl]);
    }
    
    public function findPlural($count)
    {
        $data = $this->getData();
        foreach ($data['pluralRules'] as $k => $expr)
        {
            $x = $expr = preg_replace('/\bn\b/', $count, $expr);
            $ret = eval($x = 'return ' . $expr . ';');
            if ($ret) return $k;
        }
        return 'other';
    }
}