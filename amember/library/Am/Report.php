<?php

/**
 * Exception during report
 * @package Am_Report
 */
class Am_Exception_Report extends Am_Exception_InternalError {} 

/**
 * An abstract report class
 * @package Am_Report
 */
abstract class Am_Report_Abstract
{
    static private $availableReports = array();
    /** @var Am_Form_Admin */
    protected $form;
    
    /** @var mixed executed query statement (PDOStatement?) */
    protected $stmt;

    const POINT_FLD = 'point';
    const POINT_DATE = 'date';

    const PERIOD_EXACT = 'exact';

    /** @var start and stop, for example start/stop date */
    protected $start, $stop;
    /** @var Am_Report_Quant */
    protected $quantity;

    protected $id, $title, $description;

    public function __construct()
    {
    }

    /**
     * Must return the report query returning specific field names
     * without the date column and date grouping applied!
     * @see getLines
     * @see applyQueryPoints
     * @return Am_Query
     *
     */
    public function getQuery()
    {
        throw new Am_Exception_NotImplemented("override getQuery() or runQuery() method");
    }
    /** @return string "Point" field - usually dattm, date column of the table with table alias */
    public function getPointField()
    {
        throw new Am_Exception_NotImplemented("override getPointField() or, instead entire runQuery() method");
    }
    public function getPointFieldType() { return self::POINT_DATE; }
    /** @return Am_Report_Line[] lines of current report */
    abstract public function getLines();


    /**
     * Add elements to config form
     * no need to add "time" controls
     */
    protected function createForm()
    {
        $form = new Am_Form_Admin('form-'.$this->getId());
        $form->addDataSource(new HTML_QuickForm2_DataSource_Array($this->getFormDefaults()));
        $form->setAction(REL_ROOT_URL . '/admin-reports/run/report_id/'.$this->getId());
        if ($this->getPointFieldType() == self::POINT_DATE)
        {
            $period = $form->addSelect('period')->setLabel(___('Period'))
                ->loadOptions(
                    array_merge($this->getDi()->interval->getOptions(), array(self::PERIOD_EXACT=> ___('Exact'))));

            $period_exact = self::PERIOD_EXACT;
            $script = <<<CUT
$(function(){
    $('select[name=period]').change(function(){
        $(this).closest('.am-form').find('input[name=start], input[name=stop]').
            closest('div.row').
            toggle($(this).val() == '{$period_exact}');
    }).change();
})
CUT;
            $form->addScript()->setScript($script);

            $start = $form->addElement('Date', 'start')->setLabel(___('Start'));
            $start->addRule('required');
            $stop  = $form->addElement('Date', 'stop')->setLabel(___('End'));
            $stop->addRule('required');
            $form->addRule('callback', 'Start Date cannot be later than the End Date', array($this, 'checkStopDate'));
            $quant = $form->addElement('Select', 'quant')->setLabel(___('Quantity'));
            $quant->addRule('required');
            $quant->loadOptions($this->getQuantityOptions());
        }
        $this->_initConfigForm($form);
        $form->addSubmit('save', array('value'=>___('Run Report')));
        return $form;
    }
    
    protected function _initConfigForm(Am_Form $form)
    {
        // to override
    }
    
    function checkStopDate($val){   
        $res = $val['stop']>$val['start'];
        if (!$res) {
            $elements = $this->getForm()->getElementsByName('start');
            $elements[0]->setError('Start Date cannot be later than the End Date');
        }
        return $res;
    }
    function hasConfigErrors() {
        return !$this->getForm()->validate();
    }
    
    function getFormDefaults()
    {
        if ($this->getPointFieldType() == self::POINT_DATE)
        {
            return array(
                'start' => sqlDate('-1 month'),
                'stop'  => sqlDate('now'),
            );
        } else {
            return array();
        }
    }
    function applyConfigForm(Am_Request $request)
    {
        $form = $this->getForm();
        $form->setDataSources(array($request));
        $values = $form->getValue(); // get filtered input
        $this->processConfigForm($values);
    }
    function getStartStop(array $values)
    {
        switch ($values['period']) {
            case self::PERIOD_EXACT :
                return array($values['start'], $values['stop']);
            default :
                return $this->getDi()->interval->getStartStop($values['period']);
        }
    }

    function processConfigForm(array $values)
    {
        if ($this->getPointFieldType() == self::POINT_DATE)
        {
            list($start, $stop) = $this->getStartStop($values);
            $this->setInterval($start, $stop);
            $quant = Am_Report_Quant::createById($values['quant'], $this->getPointFieldType());
            $this->setQuantity($quant);
        }
    }
    
    /**
     * @return Am_Form_Admin
     */
    function getForm()
    {
        if (!$this->form)
            $this->form = $this->createForm();
        return $this->form;
    }

    function getQuantityOptions()
    {
        $res = array();
        foreach (Am_Report_Quant::getAvailableQuants($this->getPointFieldType()) as $q)
            $res[$q->getId()] = $q->getTitle();
        return $res;
    }

    function setInterval($start, $stop)
    {
        if ($this->getPointFieldType() == self::POINT_DATE)
        {
            $start = date('Y-m-d 00:00:00', strtotime($start));
            $stop = date('Y-m-d 23:59:59', strtotime($stop));
        }
        $this->start = $start;
        $this->stop = $stop;
        return $this;
    }
    function getStart() { return $this->start; }
    function getStop()  { return $this->stop; }
    function applyQueryInterval(Am_Query $q)
    {
        $dateField = $this->getPointField();
        $f = $this->quantity->getSqlExpr($dateField);
        $q->addField($f, self::POINT_FLD);
        $q->groupBy(self::POINT_FLD, "");
        $q->addWhere("$dateField BETWEEN ? AND ?", $this->start, $this->stop);
    }
    function setQuantity(Am_Report_Quant $quantity)
    {
        $this->quantity = $quantity;
        return $this;
    }
    /** @var Am_Report_Quant */
    function getQuantity()
    {
        return $this->quantity;
    }
    /** @return Am_Report_Result */
    public function getReport(Am_Report_Result $result = null) {
        if ($result === null)
        {
            $result = new Am_Report_Result;
            $result->setTitle($this->getTitle());
        }
        $result->setQuantity($this->getQuantity());
        foreach ($this->getLines() as $line)
            $result->addLine($line);
        $this->runQuery();
        while ($r = $this->fetchNextRow())
        {
            $k = $r[self::POINT_FLD];
            unset($r[self::POINT_FLD]);
            $result->addValues($k, $this->getQuantity()->getLabel($k), $r);
        }
        return $result;
    }
    
    function fetchNextRow()
    {
        return $this->getDi()->db->fetchRow($this->stmt);
    }
    
    /** @return Am_Di */
    function getDi()
    {
        return Am_Di::getInstance();
    }

    /**
     * @return PDOStatement
     */
    protected function runQuery()
    {
        $q = $this->getQuery();
        $this->applyQueryInterval($q);
        $this->stmt = $q->query();
    }

    function getId()
    {
        if (!empty($this->id)) return $this->id;
        return lcfirst(str_ireplace('Am_Report_', '', get_class($this)));
    }
    function getTitle()
    {
        if (!empty($this->title)) return $this->title;
        return ucfirst($this->getId());
    }
    function getDescription()  {
        if (!empty($this->description)) return $this->description;
    }
    static function getAvailableReports()
    {
        
        Am_Di::getInstance()->hook->call(Am_Event::LOAD_REPORTS);
        
        if (!self::$availableReports)
            foreach (amFindSuccessors(__CLASS__) as $c)
                self::$availableReports[] = new $c;
        return self::$availableReports;
    }
    /** @return Am_Report_Abstract */
    static function createById($id)
    {
        foreach (self::getAvailableReports() as $r)
            if ($r->getId() == $id)
                return clone $r;
    }
    /** @return Am_Report_Output[] */
    public function getOutput(Am_Report_Result $result)
    {
        return array(
            new Am_Report_Graph_Line($result),
            new Am_Report_Table($result)
        );
    }
}

/**
 * Report period quantity to group results by axis X 
 */
abstract class Am_Report_Quant
{
    static $quantsList = array();

    protected $sqlExpr = null;
    function getId()
    {
        return lcfirst(str_ireplace('Am_Report_Quant_', '', get_class($this)));
    }
    function getTitle()
    {
        return ucfirst($this->getId());
    }
    function getSqlExpr($pointField)
    {
        return sprintf($this->sqlExpr, $pointField);
    }
    abstract function getPointFieldType();

    static function getAvailableQuants($pointType)
    {
        if (!isset(self::$quantsList[$pointType]))
        {
            self::$quantsList[$pointType] = array();
            foreach (amFindSuccessors(__CLASS__) as $c)
            {
                $o = new $c;
                if ($o->getPointFieldType() == $pointType)
                    self::$quantsList[$pointType][] = $o;
                else
                    unset($o);
            }
        }
        return self::$quantsList[$pointType];
    }
    static function createById($id,$pointType)
    {
        foreach (self::getAvailableQuants($pointType) as $q)
            if ($q->getId() == $id)
                return clone $q;
    }
    
    /** return human readable label */
    abstract function getLabel($key);
    /** get params for X axis of morris line*/
    abstract function getLineAxisParams();
    /** format value for X axis of morris line graph */
    abstract function formatKey($key, $graphType = 'line'); 
}

abstract class Am_Report_Quant_Date extends Am_Report_Quant
{
    public function getPointFieldType() {
        return Am_Report_Abstract::POINT_DATE;
    }
}

class Am_Report_Quant_Day extends Am_Report_Quant_Date
{
    protected $sqlExpr = "CAST(%s as DATE)";
    
    public function getTitle()
    {
        return ___("Day");
    }
    public function getLabel($key) 
    {
        return sqlDate($key);
    }
    public function formatKey($key, $graphType = 'line')
    {
        return strtotime($key) * 1000;
    }
    public function getLineAxisParams()
    {
        return array('xLabels' => 'day');
    }
}

class Am_Report_Quant_Week extends Am_Report_Quant_Date
{
    protected $sqlExpr = "YEARWEEK(%s, 3)";
    
    public function getTitle()
    {
        return ___("Week");
    }
    public function getKeyAndLabel($tm1, $tm2) {
        return array(date('YW',$tm1), amDate($tm1).' - '.amDate($tm2));
    }

    protected function getStart($key)
    {
        return strtotime(sprintf('%04d-01-01 +%04d week', substr($key,0,4), substr($key, 4,2)));
    }
    
    public function formatKey($key, $graphType = 'line')
    {
        return $this->getStart($key) * 1000;
    }

    public function getLabel($key)
    {
        $tm1 = $this->getStart($key);
        return amDate($tm1).'-'.amDate($tm1+6*24*3600); // @todo fix last year week?
    }

    public function getLineAxisParams()
    {
        
    }

}
class Am_Report_Quant_Month extends Am_Report_Quant_Date
{
    
    public function getTitle()
    {
        return ___("Month");
    }
    public function  getKeyAndLabel($tm1, $tm2) {
        return array(date('Ym',$tm1), date('M Y', $tm1));
    }
    public function getSqlExpr($dateField) {
        return "DATE_FORMAT($dateField, '%Y%m')";
    }

    protected function getStart($key)
    {
        return strtotime(sprintf('%04d-%02d-01 00:00:00', substr($key,0,4), substr($key, 4,2)));
    }

    public function formatKey($key, $graphType = 'line')
    {
        return date('Y-m', $this->getStart($key));
    }

    public function getLabel($key)
    {
        return date('M Y', $this->getStart($key));
    }

    public function getLineAxisParams()
    {
        return array('xLabels' => 'month');
    }
}

class Am_Report_Quant_Year extends Am_Report_Quant_Date
{
    protected $sqlExpr = "YEAR(%s)";
    
    public function getTitle()
    {
        return ___("Year");
    }

    public function formatKey($key, $graphType = 'line')
    {
        return $key;
    }

    public function getLabel($key)
    {
        return $key;
    }

    public function getLineAxisParams()
    {
        return array('xLabels' => 'year');
    }
}

class Am_Report_Point
{
    protected $key;
    protected $label;
    protected $values = array();
    public function __construct($key, $label) {
        $this->key = $key;
        $this->label = $label;
    }
    public function getKey()
    {
        return $this->key;
    }
    public function getLabel()
    {
        return $this->label;
    }
    public function addValue($k, $v)
    {
        isset($this->values[$k]) ?
                $this->values[$k]+=$v :
                $this->values[$k] = $v;
    }
    public function addValues(array $values)
    {
        foreach ($values as $k => $v)
            isset($this->values[$k]) ?
                $this->values[$k]+=$v :
                $this->values[$k] = $v;
    }
    public function getValue($k)
    {
        return isset($this->values[$k]) ? $this->values[$k] : null;
    }
    public function hasValues()
    {
        return (bool)$this->values;
    }
}
class Am_Report_Result
{
    protected $points = array();
    protected $lines = array();
    protected $title = "Report";
    /** @var Am_Report_Quant */
    protected $quantity;

    public function addPoint(Am_Report_Point $p)
    {
        $this->points[$p->getKey()] = $p;
        return $p;
    }
    public function addValues($pointKey, $pointLabel, array $values)
    {
        if (empty($this->points[$pointKey]))
            $this->addPoint(new Am_Report_Point($pointKey, $pointLabel));
        $this->points[$pointKey]->addValues($values);
    }
    public function addLine(Am_Report_Line $line)
    {
        $this->lines[$line->getKey()] = $line;
    }
    public function getLines()
    {
        return $this->lines;
    }
    public function getPoints()
    {
        return $this->points;
    }
    public function getPointsWithValues()
    {
        $ret = array();
        foreach ($this->points as $p)
            if ($p->hasValues()) $ret[] = $p;
        return $ret;
    }
    public function getValues($key)
    {
        $ret = array();
        foreach ($this->points as $p) $ret[] = doubleval($p->getValue($key));
        return $ret;
    }
    public function getLabels()
    {
        $ret = array();
        foreach ($this->points as $p) $ret[] = $p->getLabel();
        return $ret;
    }
    public function getRange($key)
    {
        $vals = $this->getValues($key);
        if (!$vals) $vals = array(0);
        $min = $max = $vals[0];
        foreach ($vals as $v)
        {
            if ($min>$v) $min=$v;
            if ($max<$v) $max=$v;
        }
        return array($min, $max); 
    }
    public function setTitle($title){ $this->title = $title; }
    public function getTitle() { return $this->title; }
    public function setQuantity(Am_Report_Quant $quant) { $this->quantity = $quant; }
    public function getQuantity() { return $this->quantity; }
    
    /**
     * Sort points by keys. By default name sort will be used. 
     * @param callback $cmpFunction
     * @return Am_Report_Result
     */
    public function sortPoints($cmpFunction=null){
        uksort(
            $this->points, 
            $cmpFunction ? $cmpFunction : create_function('$a, $b', 'if ($a == $b) {return 0;} return ($a < $b) ? -1 : 1;')
            );
        return $this;
    }
}

class Am_Report_Line
{
    static $colors = array(
        '#ff0000',
        '#00ff00',
        '#0000ff',
        '#ffff00',
        '#00ffff',
        '#ff00ff',
        '#990099',
        '#999900',
    );
    protected $key;
    protected $label;
    protected $color;
    public function __construct($key, $label, $color = null) {
        $this->key = $key;
        $this->label = $label;
        $this->color = $color ? $color : self::generateColor();
    }
    function getKey(){ return $this->key; }
    function getLabel() { return $this->label; }
    function getColor() { return $this->color; }
    static function generateColor()
    {
        return array_pop(self::$colors);
    }
}

/**
 * Abstract report output
 * @package Am_Report 
 */
abstract class Am_Report_Output
{
    protected $title = "Report Output";
    /** @var Am_Report_Result */
    protected $result;
    protected $divId;
    public function __construct(Am_Report_Result $result) {
        $this->result = $result;
        $this->divId = 'report-' . substr(sha1(sprintf('%s-%s', microtime(), spl_object_hash($this))), 0, 5);
    }
    /** @return string */
    abstract public function render();
    public function getTitle()  { return $this->title . ' ' . $this->result->getTitle() ; }
}

/**
 * Table output 
 */
class Am_Report_Table extends Am_Report_Output
{
    protected $title = "Table";
    public function render()
    {
        $out  = "<div class='grid-container'>\n";
        $out .= "<table class='grid'>\n";
        $out .= "<tr>\n";
        $out .= "<th>Date</th>\n";
        foreach ($this->result->getLines() as $line)
            $out .= "<th align='right'>" . Am_Controller::escape($line->getLabel()) . "</th>\n";
        $out .= "</tr>\n";
        $totals = array();
        foreach ($this->result->getPoints() as $point)
        {
            if (!$point->hasValues()) continue;
            $out .= "<tr>";
            $out .= "<td>" . Am_Controller::escape($point->getLabel()) . "</td>";
            $i = 0;
            foreach ($this->result->getLines() as $line)
            {
                $out .= sprintf("<td style='text-align: right'>%s</td>", Am_Controller::escape($point->getValue($line->getKey())));
                @$totals[ $i++ ] += $point->getValue($line->getKey());
            }
            $out .= "</tr>\n";
        }
        foreach ($totals as $tt) 
        {
            // if we have at least one numeric value in totals, display total row
            if ($tt > 0) 
            {
                $out .= "<tr class='am-report-total'><td><b>" . ___("Total") . "</b></td>";
                foreach ($totals as $v)
                    $out .= "<td style='text-align: right'><b>" . Am_Controller::escape($v) . "</b></td>";
                $out .= "</tr>\n";
                break;
            }
        }
        $out .= "</table>\n";
        $out .= "</div>";
        return $out;
    }
}

/**
 * Text report output 
 */
class Am_Report_Text extends Am_Report_Output
{
    protected $title = "Text";
    public function render()
    {
        $out  = "Date";
        foreach ($this->result->getLines() as $line)
            $out .= " / " . $line->getLabel();
        $out .= "\n";
        foreach ($this->result->getPoints() as $point)
        {
            if (!$point->hasValues()) continue;
            $out .= $point->getLabel();
            foreach ($this->result->getLines() as $line)
                $out .= " / " . $point->getValue($line->getKey());
            $out .= "\n";
        }
        return $out;
    }
}

/**
 * Graphical report output 
 */
abstract class Am_Report_Graph extends Am_Report_Output
{
    protected $title = "Graph";
    /** @var Am_Report_Result */
    protected $width = '100%';
    protected $height = 600;
    public function setSize($w, $h)
    {
        $this->width = $w;
        $this->height = $h;
        return $this;
    }
    protected function getWidth()
    {
        return is_numeric($this->width) ? $this->width . 'px' : $this->width;
    }

    protected function getHeight()
    {
        return is_numeric($this->height) ? $this->height . 'px' : $this->height;
    }

    public function render()
    {
        $ret = $this->getData();
        $ret['element'] = $this->divId;
        
        $class = $ret['class'];
        unset($ret['class']);
        
        $options = Am_Controller::getJson($ret);
        return <<<CUT
<div id='{$this->divId}' style='width: {$this->getWidth()}; height: {$this->getHeight()};'></div>
<script type='text/javascript'>
new $class($options);
</script>
CUT;
    }
    abstract function getData();
}

/**
 * A graph line 
 */
class Am_Report_Graph_Line extends Am_Report_Graph
{
    function getData()
    {
        // prepare data
        $series = array();
        $keys = array();
        $lines = $this->result->getLines();
        
        foreach ($this->result->getPoints() as $p)
        {
            $keys[] = $p->getKey();
            $i = 0;
            $k = $p->getKey();
            $k = $this->result->getQuantity()->formatKey($k, 'line');
            $d = array(
                'x' => $k,
            );
            foreach ($lines as $line)
            {
                $v = $p->getValue($line->getKey());
                if ($v !== null) $v = floatval($v);
                $d['y' . $i++] = $v;
            }
            $series[] = $d;
        }
        
        /// build config
        $config = array(
            'class' => 'Morris.Line',
            'data' => $series,
            'xkey' => 'x',
//            'title' => array('text' => $this->getTitle() ),
        );
        $config = array_merge($config, (array)$this->result->getQuantity()->getLineAxisParams());
        $i = 0;
        foreach ($this->result->getLines() as $line)
        {
            $config['ykeys'][]  = 'y' . ($i++);
            $config['labels'][] = $line->getLabel();
        }
        return $config;
    }
}

/**
 * A graph bar 
 */
class Am_Report_Graph_Bar extends Am_Report_Graph
{
    function getData()
    {
        // prepare data
        $series = array();
        $keys = array();
        $lines = $this->result->getLines();
        foreach ($this->result->getPoints() as $p)
        {
            $keys[] = $p->getKey();
            $i = 0;
            $k = $p->getKey();
            $d = array(
                'x' => $p->getLabel(),
            );
            foreach ($lines as $line)
            {
                $v = $p->getValue($line->getKey());
                if ($v !== null) $v = floatval($v);
                $d['y' . $i++] = $v;
            }
            $series[] = $d;
        }
        /// build config
        $config = array(
            'class' => 'Morris.Bar',
            'data' => $series,
            'xkey' => 'x',
//            'title' => array('text' => $this->getTitle() ),
        );
        $i = 0;
        foreach ($this->result->getLines() as $line)
        {
            $config['ykeys'][]  = 'y' . ($i++);
            $config['labels'][] = $line->getLabel();
        }
        return $config;
    }
}

