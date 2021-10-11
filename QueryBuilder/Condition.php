<?php
/**
 * This file is a part of SebkSmallOrmCore
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\QueryBuilder;

use Sebk\SmallOrmCore\Dao\Field;
use Sebk\SmallOrmCore\QueryBuilder\ConditionException;

/**
 * Unitary condition
 */
class Condition
{
    const TYPE_VALUE    = "value";
    const TYPE_FIELD    = "field";
    const TYPE_SUBQUERY = "subquery";
    const TYPE_ARRAY    = "array";
    const TYPE_NULL     = "null";
    const TYPE_CONSTANT = "constant";
    const TYPE_TUPLE = "tuple";

    protected $var1;
    protected $type1;
    protected $var2;
    protected $type2;
    protected $operator;

    public function __construct($var1, $operator, $var2 = null)
    {
        $this->type1    = $this->getVarType($var1);
        $this->type2    = $this->getVarType($var2);
        $this->checkOperator($operator);
        $this->operator = $operator;
        $this->var1     = $var1;
        $this->var2     = $var2;
    }
    
    public function __clone()
    {
        switch($this->type1) {
            case static::TYPE_SUBQUERY:
                $this->var1 = clone $this->var1;
                break;
        }
        
        switch($this->type2) {
            case static::TYPE_SUBQUERY:
                $this->var2 = clone $this->var2;
                break;
        }
    }

    public function getVarType($var)
    {
        if (is_array($var)) {
            return static::TYPE_ARRAY;
        }

        if ($var instanceof ConditionField) {
            return static::TYPE_FIELD;
        }

        if ($var instanceof QueryBuilder) {
            return static::TYPE_SUBQUERY;
        }

        if (is_object($var)) {
            throw new ConditionException("Object of type '".get_class($var)."' is not possible as condition variable");
        }

        if ($var === null) {
            return static::TYPE_NULL;
        }

        if (substr($var, 0, 1) == ":") {
            return static::TYPE_VALUE;
        }

        return static::TYPE_CONSTANT;
    }

    public function checkOperator($operator)
    {
        switch (strtolower($operator)) {
            case "=":
            case "<":
            case ">":
            case "<=":
            case ">=":
            case "!=":
            case "<>":
            case "%":
            case "like":
            case "not like":
            case "not regexpr":
            case "regexpr":
                if (!in_array($this->type1,
                        array(
                        static::TYPE_FIELD,
                        static::TYPE_VALUE,
                        static::TYPE_SUBQUERY,
                        static::TYPE_CONSTANT,
                        )
                    )) {
                    throw new ConditionException("Variable of type '".$this->type1."' is not possible with operator '$operator'");
                }
                if (!in_array($this->type2,
                        array(static::TYPE_FIELD, static::TYPE_VALUE, static::TYPE_SUBQUERY,
                        static::TYPE_CONSTANT))) {
                    throw new ConditionException("Variable of type '".$this->type2."' is not possible with operator '$operator'");
                }
                break;

            case "is":
            case "is not":
                if (!in_array($this->type1,
                        array(static::TYPE_FIELD, static::TYPE_VALUE, static::TYPE_SUBQUERY,
                        static::TYPE_CONSTANT))) {
                    throw new ConditionException("Variable of type '".$this->type1."' is not possible as left operator for operator '$operator'");
                }/*
                if ($this->type2 == static::TYPE_NULL) {
                    throw new ConditionException("Variable of type '".$this->type2."' is not possible as right operator for operator '$operator'");
                }*/
                break;

            case "exists":
            case "not exists":
                if ($this->type1 != static::TYPE_SUBQUERY) {
                    throw new ConditionException("Only subquery can be used with operator '$operator'");
                }
                if ($this->type2 != static::TYPE_NULL) {
                    throw new ConditionException("Only one subquery is possible with operator '$operator'");
                }
                break;

            case "in":
            case "not in":
                if (!in_array($this->type1,
                        array(static::TYPE_FIELD, static::TYPE_VALUE, static::TYPE_CONSTANT))) {
                    throw new ConditionException("Variable of type '".$this->type1."' is not possible as left operator for operator '$operator'");
                }
                if (!in_array($this->type2,
                        array(static::TYPE_SUBQUERY, static::TYPE_ARRAY))) {
                    throw new ConditionException("Variable of type '".$this->type2."' is not possible as right operator for operator '$operator'");
                }
                break;

            default:
                throw new ConditionException("Operator '$operator' isn't managed by query builder");
        }
    }

    protected function getSqlForVar($type, $var)
    {
        $sql = "";
        switch ($type) {
            case static::TYPE_FIELD:
                $sql .= $var->getSql();
                break;

            case static::TYPE_VALUE:
                $sql .= $var;
                break;

            case static::TYPE_NULL:
                $sql .= "NULL";
                break;

            case static::TYPE_ARRAY:
                $sql .= "('".implode("', '", $var)."')";
                break;

            case static::TYPE_SUBQUERY:
                $sql .= "(".$var->getSql().")";
                break;

            case static::TYPE_CONSTANT:
                $sql .= "'".addslashes($var)."'";
        }

        return $sql;
    }

    public function getSql()
    {
        $sql = " ";

        switch (strtolower($this->operator)) {
            case "=":
            case "<":
            case ">":
            case "<=":
            case ">=":
            case "!=":
            case "<>":
            case "%":
            case "like":
            case "not like":
            case "not regexpr":
            case "regexpr":
            case "is":
            case "is not":
            case "in":
            case "not in":
                $sql .= $this->getSqlForVar($this->type1, $this->var1);
                $sql .= " ".strtoupper($this->operator)." ";
                $sql .= $this->getSqlForVar($this->type2, $this->var2);
                break;

            case "exists":
            case "not exists":
                $sql .= " ".strtoupper($this->operator)." ";
                $sql .= $this->getSqlForVar($this->type1, $this->var1);
                break;

            default:
                throw new ConditionException("Operator '".$this->operator."' is not managed");
        }

        return $sql;
    }
}