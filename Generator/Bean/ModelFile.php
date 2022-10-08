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
            if (file_exists($this->selector->getDaoFolder() . "/" . $this->getClassnameForTable($toOne["toTable"]) . ".php")) {
                if (!$hasToOne) {
                    $getters .= "\n    // To one relations getters";
                    $hasToOne = true;
                }
                $getters .= $this->toOneOrManyGetter($toOne["toTable"], $fields);
            }
        }

        // To many relations
        $hasToMany = false;
        foreach ($this->dbGateway->getToManys($this->table) as $toMany) {
            if (file_exists($this->selector->getDaoFolder() . "/" . $this->getClassnameForTable($toMany["toTable"]) . ".php")) {
                if (!$hasToMany) {
                    $getters .= "\n    // To many relations getters";
                    $hasToOne = true;
                }
                $getters .= $this->toOneOrManyGetter($toMany["toTable"], $fields, true);
            }
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
        $fields = [];
        foreach ($description as $record) {
            if($record["Key"] != "PRI") {
                $fields[] = ucfirst(static::getDaoFieldname($record["Field"]));
                $setters .= $this->fieldSetter($record["Field"], $this->getPhpType($record));
            }
        }

        // To one relations
        $hasToOne = false;
        foreach ($this->dbGateway->getToOnes($this->table) as $toOne) {
            if (file_exists($this->selector->getDaoFolder() . "/" . $this->getClassnameForTable($toOne["toTable"]) . ".php")) {
                if (!$hasToOne) {
                    $setters .= "\n    // To one relations setters";
                    $hasToOne = true;
                }
                if (in_array($this->getClassnameForTable($toOne["toTable"]), $fields)) {
                    $setter = $this->getClassnameForTable($toOne["toTable"]) . "Relation";
                } else {
                    $setter = $this->getClassnameForTable($toOne["toTable"]);
                }
                $setters .= $this->toOneSetter($toOne["toTable"], $setter);
            }
        }

        // To many relations
        $hasToMany = false;
        foreach ($this->dbGateway->getToManys($this->table) as $toMany) {
            if (file_exists($this->selector->getDaoFolder() . "/" . $this->getClassnameForTable($toMany["toTable"]) . ".php")) {
                if (!$hasToMany) {
                    $setters .= "\n    // To many relations setters";
                    $hasToMany = true;
                }
                $setters .= $this->addToCollection($this->getClassnameForTable($toMany["toTable"]));
            }
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

    /**
     * @param string $fieldName
     * @param $type
     * @return string
     */
    public function toOneSetter(string $table, string $setter): string
    {
        $type = $this->getClassnameForTable($table);

        return "
    /**
     * @param $type $" . lcfirst($this->getClassnameForTable($table)) . "
     * @return \$this
     */
    public function set$setter($type $" . lcfirst($this->getClassnameForTable($table)) . ")
    {
        parent::set$setter($" . lcfirst($this->getClassnameForTable($table)) . ");
        
        return \$this;
    }
    ";
    }

    public function toOneOrManyGetter(string $fieldname, array $fields, $pluralize = false): string
    {
        $baseMethod = $pluralize ? lcfirst(static::pluralize($this->getClassnameForTable($fieldname))) : lcfirst($this->getClassnameForTable($fieldname));
        $method = in_array($baseMethod, $fields)
            ? $this->getClassnameForTable($fieldname, $pluralize) . 'Relation'
            : $this->getClassnameForTable($fieldname, $pluralize);

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

    public function addToCollection(string $class): string
    {
        return "
    /**
     * @param $class $" . lcfirst($class) . "
     * @return \$this
     */
    public function add" . $class . "($class $" . lcfirst($class) . ")
    {
        \$collection = parent::get" . self::pluralize($class) . "();
        \$collection[] = $" . lcfirst($class) . ";
        parent::set" . self::pluralize($class) . "($" . lcfirst($class) . ");
        
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