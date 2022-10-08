<?php

namespace Sebk\SmallOrmCore\Generator\Bean;

class ModelFile extends AbstractPhpFile
{

    protected static $modelTemplate = "<?php
namespace [namespace];

use Sebk\SmallOrmCore\Dao\Model;
[uses]

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
     * Generate all getters
     * @param $dbTableName
     * @return string
     * @throws \Exception
     */
    public function generateGetters()
    {
        $description = $this->dbGateway->getDescription($this->table);

        $getters = "// Fields getters";
        $fields = [];
        foreach ($description as $record) {
            $fields[] = static::camelize($record["Field"], true);
            $getters .= $this->fieldGetter($record["Field"], $this->getPhpType($record));
        }

        // To one relations
        $hasToOne = false;
        foreach ($this->dbGateway->getToOnes($this->table) as $toOne) {
            if (!$hasToOne) {
                $getters .= "\n    // To one relations getters";
                $hasToOne = true;
            }
            $getters .= $this->toOneOrManyGetter($toOne["toTable"], $fields);
        }

        // To many relations
        $hasToMany = false;
        foreach ($this->dbGateway->getToManys($this->table) as $toMany) {
            if (!$hasToMany) {
                $getters .= "\n    // To many relations getters";
                $hasToOne = true;
            }
            $getters .= $this->toOneOrManyGetter($toMany["toTable"], $fields, true);
        }

        return $getters;
    }

    /**
     * Generate all setters
     * @param $dbTableName
     * @return string
     * @throws \Exception
     */
    public function generateSetters()
    {
        $description = $this->dbGateway->getDescription($this->table);

        $setters = "// Fields setters";
        foreach ($description as $record) {
            if($record["Key"] != "PRI") {
                $setters .= $this->fieldSetter($record["Field"], $this->getPhpType($record));
            }
        }

        // To one relations
        $hasToOne = false;
        foreach ($this->dbGateway->getToOnes($this->table) as $toOne) {
            if (!$hasToOne) {
                $setters .= "\n    // To one relations setters";
                $hasToOne = true;
            }
            $setters .= $this->fieldSetter($toOne["toTable"], $this->getClassName($toOne["toTable"]));
        }

        return $setters;
    }

    public function generateUses() {
        $uses = "";

        $toOnes = $this->dbGateway->getToOnes($this->table);
        foreach ($toOnes as $toOne) {
            $uses .= "use " . $this->namespace . "\\" . $this->getClassnameForTable($toOne["toTable"]) . ";\n";
        }

        $toManys = $this->dbGateway->getToManys($this->table);
        foreach ($toManys as $toMany) {
            $uses .= "use " . $this->namespace . "\\" . $this->getClassnameForTable($toMany["toTable"]) . ";\n";
        }

        return $uses;
    }

    /**
     * @param string $fieldName
     * @param $type
     * @return string
     */
    public function fieldGetter(string $fieldname, $type): string
    {
        return "
    /**
     * @return $type
     */
    public function get" . ucfirst(static::getDaoFieldname($fieldname)) . "()
    {
        return parent::get" . ucfirst(static::getDaoFieldname($fieldname)) . "();
    }
    ";
    }

    public function toOneOrManyGetter(string $fieldname, array $fields, $pluralize = false): string
    {
        $method = in_array(lcfirst(static::pluralize($this->getClassnameForTable($fieldname))), $fields)
            ? $this->getClassnameForTable($fieldname) . 'Relation'
            : $this->getClassnameForTable($fieldname);

        return "
    /**
     * @return " . $this->getClassnameForTable($fieldname) . "
     */
    public function get" . $method . "()
    {
        return parent::get" . $method . "();
    }
    ";
    }

    public function fieldSetter(string $fieldname, $type): string
    {
        return "
    /**
     * @param $type $" . static::getDaoFieldname($fieldname) . "
     * @return \$this
     */
    public function set" . ucfirst(static::getDaoFieldname($fieldname)) . "($" . static::getDaoFieldname($fieldname) . ")
    {
        parent::set" . ucfirst(static::getDaoFieldname($fieldname)) . "($" . static::getDaoFieldname($fieldname) . ");
        
        return \$this;
    }
    ";
    }

    public function addToCollection(string $fieldName, $class): string
    {
        return "
    /**
     * @param $class $" . static::getDaoFieldname($fieldName) . "
     * @return \$this
     */
    public function add" . static::getDaoFieldname($fieldName) . "($class $" . static::getDaoFieldname($fieldName) . ")
    {
        parent::set" . static::getDaoFieldname($fieldName, true) . "($" . static::getDaoFieldname($fieldName) . ");
        
        return \$this;
    }";
    }

    /**
     * Get template
     * @return string
     */
    public function getTemplate(): string
    {
        return str_replace(
            "[namespace]",
            $this->namespace,
            str_replace("[modelName]",
                $this->getClassname($this->table),
                static::$modelTemplate
            )
        );
    }
}