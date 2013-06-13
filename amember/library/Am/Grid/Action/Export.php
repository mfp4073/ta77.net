<?php

/** 
 * Export interface
 * @package Am_Grid 
 */
interface Am_Grid_Export_Processor {

    public function buildForm($form);

    public function run(Am_Grid_Editable $grid, Am_Grid_DataSource_Interface_ReadOnly $dataSource, $fields, $config);
}

/**
 * Factory for export processors
 * @package Am_Grid 
 */
class Am_Grid_Export_Processor_Factory {

    protected static $elements = array();

    static public function register($id, $class, $title) {
        self::$elements[$id] = array(
            'class' => $class,
            'title' => $title
        );
    }

    static public function create($id) {
        if (isset(self::$elements[$id]))
            return new self::$elements[$id]['class'];
        throw new Am_Exception_InternalError(sprintf('Can not create object for id [%s]'), $id);
    }

    static public function createAll() {
        $res = array();
        foreach (self::$elements as $id => $desc) {
            $res[$id] = new $desc['class'];
        }
        return $res;
    }

    static public function getOptions() {
        $options = array();
        foreach (self::$elements as $id => $desc) {
            $options[$id] = $desc['title'];
        }
        return $options;
    }

}

/**
 * Export as CSV file
 * @package Am_Grid 
 */
class Am_Grid_Export_CSV implements Am_Grid_Export_Processor {
    const EXPORT_REC_LIMIT = 1024;

    public function buildForm($form) {
        $form->addElement('text', 'delim', array('size' => 3, 'value' => ','))
                ->setLabel('Fields delimited by');
    }

    public function run(Am_Grid_Editable $grid, Am_Grid_DataSource_Interface_ReadOnly $dataSource, $fields, $config) {

        header('Cache-Control: maxage=3600');
        header('Pragma: public');
        header("Content-type: application/csv");
        $dat = date('YmdHis');
        header("Content-Disposition: attachment; filename=amember".$grid->getId()."-$dat.csv");

        $total = $dataSource->getFoundRows();
        $numOfPages = ceil($total / self::EXPORT_REC_LIMIT);
        $delim = $config['delim'];

        //render headers
        foreach ($fields as $field) {
            echo $this->filterValue(
                    $field->getFieldTitle(), $delim
            ) . $delim;
        }
        echo "\r\n";

        //render content
        for ($i = 0; $i < $numOfPages; $i++) {
            $ret = $dataSource->selectPageRecords($i, self::EXPORT_REC_LIMIT);
            foreach ($ret as $r) {
                foreach ($fields as $field) {
                    echo $this->filterValue(
                            $field->get($r, $grid), $delim
                    ) . $delim;
                }
                echo "\r\n";
            }
        }
        return;
    }

    private function filterValue($value, $delim) {
        $result = $value;
        $result = str_replace("\n", "", $value);
        $result = str_replace("\r", "", $value);

        if (strstr($result, $delim) ||
                strstr($result, '"')) {

            $result = sprintf('"%s"', str_replace('"', '""', $result));
        };

        return $result;
    }

}

/**
 * Export as XML
 * @package Am_Grid 
 */
class Am_Grid_Export_XML implements Am_Grid_Export_Processor {
    const EXPORT_REC_LIMIT = 1024;

    public function buildForm($form) {
        //nop
    }

    public function run(Am_Grid_Editable $grid, Am_Grid_DataSource_Interface_ReadOnly $dataSource, $fields, $config) {

        header('Cache-Control: maxage=3600');
        header('Pragma: public');
        header("Content-type: application/xml");
        $dat = date('YmdHis');
        header("Content-Disposition: attachment; filename=amember".$grid->getId()."-$dat.xml");

        $total = $dataSource->getFoundRows();
        $numOfPages = ceil($total / self::EXPORT_REC_LIMIT);

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument();

        $xml->startElement('export');
        for ($i = 0; $i < $numOfPages; $i++) {
            $ret = $dataSource->selectPageRecords($i, self::EXPORT_REC_LIMIT);
            foreach ($ret as $r) {
                $xml->startElement('row');
                foreach ($fields as $field) {
                    $xml->startElement('field');
                    $xml->writeAttribute('name', $field->getFieldTitle());
                    $xml->text($field->get($r, $grid));
                    $xml->endElement(); // field
                }
                $xml->endElement();
            }
        }
        $xml->endElement();
        echo $xml->flush();
        return;
    }

}

Am_Grid_Export_Processor_Factory::register('csv', 'Am_Grid_Export_CSV', 'CSV');
Am_Grid_Export_Processor_Factory::register('xml', 'Am_Grid_Export_XML', 'XML');

/**
 * Grid action to display "export" option
 * @package Am_Grid 
 */
class Am_Grid_Action_Export extends Am_Grid_Action_Abstract 
{
    protected $privilege = 'export';
    protected $type = self::HIDDEN;
    protected $fields = array();
    protected $getDataSourceFunc = null;

    public function run() {
        $form = new Am_Form_Admin();
        $form->setAction($this->getUrl());
        $form->setAttribute('name', 'export');
        $form->setAttribute('target', '_blank');

        $form->addElement('magicselect', 'fields_to_export')
                ->loadOptions($this->getExportOptions())
                ->setLabel(___('Fields To Export'));

        $form->addElement('select', 'export_type')
                ->loadOptions(
                        Am_Grid_Export_Processor_Factory::getOptions()
                )->setLabel(___('Export Format'))
                ->setId('form-export-type');

        foreach (Am_Grid_Export_Processor_Factory::createAll() as $id => $obj) {
            $obj->buildForm($form->addElement('fieldset', $id)->setId('form-export-options-' . $id));
        }

        $form->addSubmit('export', array('value' => ___('Export')));

        $script = <<<CUT
(function($){
    $(function(){
        function update_options(\$sel) {
            $('[id^=form-export-options-]').hide();
            $('#form-export-options-' + \$sel.val()).show();
        }   
        
        update_options($('#form-export-type'));
        $('#form-export-type').bind('change', function() {
            update_options($(this));
        })

    })
})(jQuery)
CUT;
        $form->addScript('script')->setScript($script);

        $this->initForm($form);

        if ($form->isSubmitted()) {
            $values = $form->getValue();

            $fields = array();
            foreach ($values['fields_to_export'] as $fieldName) {
                $fields[$fieldName] = $this->getField($fieldName);
            }

            $export = Am_Grid_Export_Processor_Factory::create($values['export_type']);
            $export->run($this->grid, $this->getDataSource($fields), $fields, $values);
            exit;
        } else {
            echo $this->renderTitle();
            echo $form;
        }
    }

    /**
     * can be used to customize datasource to add some UNION for example
     *
     * @param type $callback 
     */
    public function setGetDataSourceFunc($callback) {
        if (!is_callable($callback))
            throw new Am_Exception_InternalError("Invalid callback in " . __METHOD__);

        $this->getDataSourceFunc = $callback;
    }

    public function addField(Am_Grid_Field $field) {
        $this->fields[] = $field;
        return $this;
    }

    /**
     *
     * @param string $fieldName 
     * @return Am_Grid_Field
     */
    public function getField($fieldName) {
        foreach ($this->getFields() as $field)
            if ($field->getFieldName() == $fieldName)
                return $field;
        throw new Am_Exception_InternalError("Field [$fieldName] not found in " . __METHOD__);
    }

    protected function getFields() {
        return count($this->fields) ? $this->fields : $this->grid->getFields();
    }

    protected function initForm($form) {
        $form->setDataSources(array(
            $this->grid->getCompleteRequest(),
        ));


        $vars = array();
        foreach ($this->grid->getVariablesList() as $k) {
            $vars[$this->grid->getId() . '_' . $k] = $this->grid->getRequest()->get($k, "");
        }
        $form->addHtml('hidden')
            ->setHtml(Am_Controller::renderArrayAsInputHiddens($vars));

    }

    /**
     * 
     * @return Am_Grid_DataSource_Interface_ReadOnly
     */
    protected function getDataSource($fields) {
        return $this->getDataSourceFunc ?
                call_user_func($this->getDataSourceFunc, $this->grid->getDataSource(), $fields) :
                $this->grid->getDataSource();
    }

    protected function getExportOptions() {

        $res = array();

        foreach ($this->getFields() as $field) {
            /* @var $field Am_Grid_Field */
            $res[$field->getFieldName()] = $field->getFieldTitle();
        }

        return $res;
    }

    public function setGrid(Am_Grid_Editable $grid) {
        $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_TABLE, array($this, 'renderLink'));
        parent::setGrid($grid);
    }

    public function renderLink(& $out) {
        $out .= sprintf('<div style="float:right">&nbsp;&nbsp;<a href="%s">'.___('Export').'</a></div>',
                $this->getUrl());
    }
}