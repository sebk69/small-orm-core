<?php
/**
 * This file is a part of SebkSmallOrmCore
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\QueryBuilder;

use Sebk\SmallOrmCore\QueryBuilder\ConditionException;

/**
 * Unitary condition
 */
class FieldUpdate
{
    const TYPE_VALUE    = "value";
    const TYPE_FIELD    = "field";
    const TYPE_SUBQUERY = "subquery";
    const TYPE_NULL     = "null";
    const TYPE_CONSTANT = "constant";

    protected $field;
    protected $update;
    protected $typeUpdate;

    /**
     * @param string $field
     * @param mixed $update
     */
    public function __construct($field, $update)
    {
        $this->field = $field;
        $this->typeUpdate    = $this->getVarType($update);
        $this->update = $update;
    }
    
    public function __clone() 
    {
        if($this->getVarType(static::TYPE_SUBQUERYY)) {
            $this->update = clone $this->update;
        }
    }

    /**
     * Get field
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Get update value
     * @return mixed
     */
    public function getUpdateValue()
    {
        return $this->update;
    }

    /**
     * Get type of update
     * @param mixed $var
     * @return string
     * @throws ConditionException
     */
    public function getVarType($var)
    {
        if (substr($var, 0, 1) == "`") {
            return static::TYPE_FIELD;
        }

        if ($var instanceof QueryBuilder) {
            return static::TYPE_SUBQUERY;
        }

        if (is_object($var)) {
            throw new UpdateException("Object of type '".get_class($var)."' is not possible as condition variable");
        }

        if ($var === null) {
            return static::TYPE_NULL;
        }

        if (substr($var, 0, 1) == ":") {
            return static::TYPE_VALUE;
        }

        return static::TYPE_CONSTANT;
    }

    /**
     * @param string $type
     * @param mixed $var
     * @return string
     */
    protected function getSqlForVar($type, $var)
    {
        $sql = "";
        switch ($type) {
            case static::TYPE_FIELD:
                $sql .= $var;
                break;

            case static::TYPE_VALUE:
                $sql .= $var;
                break;

            case static::TYPE_NULL:
                $sql .= "NULL";
                break;

            case static::TYPE_SUBQUERY:
                $sql .= "(".$var->getSql().")";
                break;

            case static::TYPE_CONSTANT:
                $sql .= "'".addslashes($var)."'";
        }

        return $sql;
    }

    /**
     * Get sql for updating field
     * @return string
     */
    public function getSql()
    {
        $sql = $this->field->getDbName()." = ".$this->getSqlForVar($this->typeUpdate, $this->update);

        return $sql;
    }
}