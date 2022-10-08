<?php

namespace Sebk\SmallOrmCore\Generator\Bean;

use Sebk\SmallOrmCore\Dao\Field;

class FieldDescription
{

    public function __construct(protected array $fieldDescription) {}

    /**
     * Get DAO type
     * @return string
     */
    public function getDaoType(): string
    {
        // Get sql type
        $sqlType = $this->fieldDescription["Type"];

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
     * Get default value
     * @return string
     */
    public function getDefaultValue(): string
    {
        switch($this->fieldDescription["Default"]) {
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
                $default = '"'.$this->fieldDescription["Default"].'"';
        }

        return $default;
    }

    /**
     * Get field name
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldDescription["Field"];
    }

}