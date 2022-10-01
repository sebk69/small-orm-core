<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Generator;


use Psr\Container\ContainerInterface;
use Sebk\SmallOrmCore\Dao\AbstractDao;
use Sebk\SmallOrmCore\Dao\DaoException;
use Sebk\SmallOrmCore\Dao\Field;
use Sebk\SmallOrmCore\Dao\ToOneRelation;
use Sebk\SmallOrmCore\Database\AbstractConnection;
use Sebk\SmallOrmCore\Factory\Connections;
use Sebk\SmallOrmCore\Factory\Dao;
use Sebk\SmallOrmCore\Factory\DaoNotFoundException;

/**
 * Class DaoGenerator
 * @package Sebk\SmallOrmCore\Generator
 */
class DaoGenerator
{
    protected string $connectionName;
    protected string $daoNameSpace;
    protected string $modelNamespace;
    protected DbGateway $dbGateway;
    protected Selector $selector;
    /** @var Selector[] */
    protected array $selectors;

    protected static $daoTemplate = "<?php
namespace [nameSpace];

use Sebk\\SmallOrmCore\\Dao\\AbstractDao;
use Sebk\\SmallOrmCore\\Dao\\Field;

class [daoName] extends AbstractDao
{
    protected function build()
    {
    }
}";

    protected static $modelTemplate = "<?php
namespace [namespace];

use Sebk\SmallOrmCore\Dao\Model;

class [modelName] extends Model
{
    
    public function onLoad() {}
    
    public function beforeSave() {}
    
    public function afterSave() {}
    
    public function beforeDelete() {}
    
    public function afterDelete() {}
    
    [getters]
    
    [setters]
    
}";

    /**
     * DaoGenerator constructor.
     */
    public function __construct(protected Connections $connections, protected ContainerInterface $container, protected array $config) {
        foreach ($this->config['selectors'] as $selectorArray) {
            $this->selectors[] = new Selector($this->config['folders'], $selectorArray);
        }
    }

    /**
     * Set class parameters
     * @param string $connectionName
     * @param Selector $selector
     * @return $this
     * @throws \Sebk\SmallOrmCore\Factory\ConfigurationException
     */
    public function setParameters(string $connectionName, Selector $selector): DaoGenerator
    {
        $this->connectionName = $connectionName;
        $this->dbGateway = new DbGateway($this->connections->get($connectionName));
        $this->selector = $selector;
        if (!is_dir($selector->getDaoFolder())) {
            mkdir($selector->getDaoFolder(), 0777, true);
        }
        if (!is_dir($selector->getModelFolder())) {
            mkdir($selector->getModelFolder(), 0777, true);
        }

        return $this;
    }

    /**
     * Get standard dao class name
     * @return string
     */
    public function getDaoClassName($table) {
        if (isset($this->config["remove_tables_namespaces"])) {
            foreach ($this->config["remove_tables_namespaces"] as $namespace) {
                if(substr($table, 0, strlen($namespace)) == $namespace) {
                    $table = substr($table, strlen($namespace));
                }
            }
        }

        return $this->camelize($table);
    }

    /**
     * Get content of dao file
     * @return string
     */
    public function getDaoFileContent($dbTableName)
    {
        $filePath = $this->selector->getDaoFolder() . '/' . $this->getDaoClassName($dbTableName) . '.php';
        if(file_exists($filePath)) {
            return file_get_contents($filePath);
        } else {
            $template = str_replace("[nameSpace]", $this->selector->getDaoNamespace(), static::$daoTemplate);
            $template = str_replace("[daoName]", $this->getDaoClassName($dbTableName), $template);

            return $template;
        }
    }

    /**
     * Put content in dao file
     * @param $content
     * @return $this
     */
    public function putDaoFileContent($dbTableName, $content)
    {
        $filePath = $this->selector->getDaoFolder() . '/' . $this->getDaoClassName($dbTableName) . '.php';
        file_put_contents($filePath, $content);

        return $this;
    }

    /**
     * Camelize string
     * @param $string
     * @param bool $firstLetterLowercase
     * @return string
     */
    private static function camelize($string, $firstLetterLowercase = false, $pluralize = false)
    {
        $parts = explode("_", $string);

        $result = "";
        foreach ($parts as $part) {
            if($pluralize) {
                $result .= ucfirst(static::pluralize($part));
            } else {
                $result .= ucfirst($part);
            }
        }

        if($firstLetterLowercase) {
            $result = lcfirst($result);
        }

        return $result;
    }

    /**
     * Return plural of a string
     * @param $singular
     * @return string
     */
    private static function pluralize($singular) {
        $last_letter = strtolower($singular[strlen($singular)-1]);
        switch($last_letter) {
            case 'y':
                return substr($singular,0,-1).'ies';
            case 's':
                return $singular.'es';
            default:
                return $singular.'s';
        }
    }

    private function getSelectorForTable(string $table)
    {
        // Is current selector has table ?
        if (file_exists($this->selector->getDaoFolder() . '/' . $this->getDaoClassName($table))) {
            return $this->selector;
        }

        // Else get first selector which have table
        foreach ($this->selectors as $selector) {
            if (file_exists($selector->getDaoFolder() . '/' . $this->getDaoClassName($table))) {
                return $selector;
            }
        }

        throw new TableNotFoundException('Table ' . $table . ' can\'t be found in any selector namespace');
    }

    /**
     * Generate build function
     * @return string
     */
    protected function generateBuildFunction($dbTableName)
    {
        // retreive database description
        $connection = $this->connections->get($this->connectionName);
        $description = $this->dbGateway->getDescription($dbTableName);

        // build function
        $output = 'protected function build()
    {
        $this->setDbTableName("'.$dbTableName.'")
            ->setModelName("'.$this->getDaoClassName($dbTableName).'")
';

        // Fields
        foreach ($description as $record) {
            // Get default
            switch($record["Default"]) {
                case "now()":
                case "CURRENT_TIMESTAMP":
                    $default = "new \DateTime()";
                    break;

                case "NULL":
                case "null":
                case "":
                    $default = "null";
                    break;

                default:
                    $default = '"'.$record["Default"].'"';
            }

            // get type
            $type = $this->getDaoTypeFromTableDescription($record);

            if($record["Key"] != "PRI") {
                $output .= '            ->addField("' . $record["Field"] . '", "' . static::camelize($record["Field"], true) . '", ' . $default.', Field::' . $type . ')
';
            } else {
                $output .= '            ->addPrimaryKey("' . $record["Field"] . '", "' . static::camelize($record["Field"], true) . '")
';
            }

        }

        // To one relations
        foreach ($this->dbGateway->getToOnes($dbTableName) as $toOne) {
            try {
                $toSelector = $this->getSelectorForTable($toOne["toTable"]);
                $output .= '            ->addToOne("' . static::camelize($toOne["toTable"], true) .
                    '", ["' . static::camelize($toOne["toField"], true) . '" => "' . static::camelize($toOne["fromField"], true) . '"], ' .
                    $toSelector->getDaoNamespace() . '\\' . $this->getDaoClassName($toOne["toTable"]) . '::class)';
            } catch(TableNotFoundException $e) {

            }
        }

        // To many relations
        foreach ($this->dbGateway->getToManys($dbTableName) as $toMany) {
            try {
                $toSelector = $this->getSelectorForTable($toMany["toTable"]);
                $output .= '            ->addToMany("'.static::camelize($toMany["toTable"], true, true).
                    '", ["'.static::camelize($toMany["toField"], true).'" => "'.static::camelize($toMany["fromField"], true).'"], ' .
                    $toSelector->getDaoNamespace() . '\\' . $this->getDaoClassName($toOne["toTable"]) . '::class)';
            } catch(TableNotFoundException $e) {}
        }

        $output .= '        ;
    }';

        return $output;
    }

    /**
     * Generate all getters
     * @param $dbTableName
     * @return string
     * @throws \Exception
     */
    private function generateGetters($dbTableName)
    {
        $description = $this->dbGateway->getDescription($dbTableName);

        $getters = "// Fields getters";
        foreach ($description as $record) {
            $getters .= $this->fieldGetter($record["Field"], $this->getPhpTypeFromTableDescription($record));
        }

        // To one relations
        $hasToOne = false;
        foreach ($this->dbGateway->getToOnes($dbTableName) as $toOne) {
            if (!$hasToOne) {
                $getters .= "\n    // To one relations getters";
                $hasToOne = true;
            }
            $getters .= $this->fieldGetter($toOne["toTable"], $this->getDaoClassName($toOne["toTable"]));
        }

        // To many relations
        $hasToMany = false;
        foreach ($this->dbGateway->getToManys($dbTableName) as $toMany) {
            if (!$hasToMany) {
                $getters .= "\n    // To many relations getters";
                $hasToOne = true;
            }
            $getters .= $this->fieldGetter($toMany["toTable"], $this->getDaoClassName($toMany["toTable"]));
        }

        return $getters;
    }

    /**
     * Generate all setters
     * @param $dbTableName
     * @return string
     * @throws \Exception
     */
    private function generateSetters($dbTableName)
    {
        $description = $this->dbGateway->getDescription($dbTableName);

        $setters = "// Fields setters";
        foreach ($description as $record) {
            if($record["Key"] != "PRI") {
                $setters .= $this->fieldSetter($record["Field"], $this->getPhpTypeFromTableDescription($record));
            }
        }

        // To one relations
        $hasToOne = false;
        foreach ($this->dbGateway->getToOnes($dbTableName) as $toOne) {
            if (!$hasToOne) {
                $setters .= "\n    // To one relations setters";
                $hasToOne = true;
            }
            $setters .= $this->fieldSetter($toOne["toTable"], $this->getDaoClassName($toOne["toTable"]));
        }

        // To many relations
        $hasToMany = false;
        foreach ($this->dbGateway->getToManys($dbTableName) as $toMany) {
            if (!$hasToMany) {
                $setters .= "\n    // To many relations setters";
            }
            $setters .= $this->addToCollection($toMany["toTable"], $this->getDaoClassName($toMany["toTable"]));
        }

        return $setters;
    }

    /**
     * Convert a sql description record to small-orm type
     * @param array $description
     * @return string
     */
    private function getDaoTypeFromTableDescription(array $description)
    {
        // Get sql type
        $sqlType = $description["Type"];

        // INT(1) is considered as boolean
        if (strtolower($sqlType) == "int(1)") {
            return Field::TYPE_BOOLEAN;
        }

        // Remove brackets
        for($i = 0; $i < strlen($sqlType); $i++) {
            if (substr($sqlType, $i, 1) == "(") {
                break;
            }
        }
        $sqlType = substr($sqlType, 0, $i);

        switch(strtolower($sqlType)) {
            case "int":
            case "smallint":
            case "mediumint":
            case "bigint":
            case "double":
                return Field::TYPE_INT;
            // TINYINT is considered as boolean
            case "tinyint":
                return Field::TYPE_BOOLEAN;
            case "datetime":
                return Field::TYPE_DATETIME;
            case "date":
                return Field::TYPE_DATE;
            case "json":
                return FIeld::TYPE_JSON;
            case "decimal":
            case "float":
                return FIeld::TYPE_FLOAT;
            case "char":
            case "longtext":
            case "mediumtext":
            case "text":
            case "varchar":
            default:
                return Field::TYPE_STRING;
        }
    }

    /**
     * Convert a sql description record to small-orm type
     * @param array $description
     * @return string
     */
    private function getPhpTypeFromTableDescription(array $description)
    {
        // Get sql type
        $sqlType = $description["Type"];

        // INT(1) is considered as boolean
        if (strtolower($sqlType) == "int(1)") {
            return Field::TYPE_BOOLEAN;
        }

        // Remove brackets
        for($i = 0; $i < strlen($sqlType); $i++) {
            if (substr($sqlType, $i, 1) == "(") {
                break;
            }
        }
        $sqlType = substr($sqlType, 0, $i);

        switch(strtolower($sqlType)) {
            case "int":
            case "smallint":
            case "mediumint":
            case "bigint":
            case "double":
                return 'int';
            // TINYINT is considered as boolean
            case "tinyint":
                return 'bool';
            case "datetime":
                return '\DateTime';
            case "date":
                return '\DateTime';
            case "json":
                return 'array';
            case "decimal":
            case "float":
                return 'float';
            case "char":
            case "longtext":
            case "mediumtext":
            case "text":
            case "varchar":
            default:
                return 'string';
        }
    }

    /**
     * recompute files for a table
     */
    public function recomputeFilesForTable($dbTableName)
    {
        // Build function for the DAO
        $content = $this->getDaoFileContent($dbTableName);
        $parser = new FileParser($content);
        $buildPos = $parser->findFunctionPos("build");
        $content = substr_replace($content, $this->generateBuildFunction($dbTableName), $buildPos["start"], $buildPos["end"] - $buildPos["start"]);
        $this->putDaoFileContent($dbTableName, $content);

        // Create model class if not exists
        $modelFile = $this->selector->getModelFolder() . '/' . $this->getDaoClassName($dbTableName) . '.php';
        if(!file_exists($modelFile)) {
            $content = str_replace(
                "[namespace]",
                $this->selector->getModelNamespace(),
                str_replace("[modelName]",
                    $this->getDaoClassName($dbTableName),
                    static::$modelTemplate
                )
            );
            $content = str_replace("[getters]", $this->generateGetters($dbTableName), $content);
            $content = str_replace("[setters]", $this->generateSetters($dbTableName), $content);
            file_put_contents($modelFile, $content);
        }

        return $this;
    }

    /**
     * @param string $fieldName
     * @param $type
     * @return string
     */
    public function fieldGetter(string $fieldName, $type): string
    {
        return "
    /**
     * @return $type
     */
    public function get" . static::camelize($fieldName) . "()
    {
        return parent::get" . static::camelize($fieldName) . "();
    }
    ";
    }

    public function fieldSetter(string $fieldName, $type): string
    {
        return "
    /**
     * @param $type $" . static::camelize($fieldName, true) . "
     * @return \$this
     */
    public function set" . static::camelize($fieldName) . "($" . static::camelize($fieldName, true) . ")
    {
        parent::set" . static::camelize($fieldName) . "($" . static::camelize($fieldName, true) . ");
        
        return \$this;
    }
    ";
    }

    public function addToCollection(string $fieldName, $class): string
    {
        return "
    /**
     * @param $class $" . static::camelize($fieldName, true) . "
     * @return \$this
     */
    public function add" . static::camelize($fieldName) . "($class $" . static::camelize($fieldName, true) . ")
    {
        parent::set" . static::camelize($fieldName) . "($" . static::camelize($fieldName, true) . ");
        
        return \$this;
    }";
    }

}
