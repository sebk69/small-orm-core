<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyrightt 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\QueryBuilder;

/**
 *
 */
class groupByOperation
{
    protected $field;
    protected $modelAlias;
    protected $operation;
    protected $operationAlias;

    /**
     *
     * @param string $operation
     * @param string $modelAlias
     * @param string $field
     * @param string $operationAlias
     * @throws QueryBuilderException
     */
    public function __construct($operation, $modelAlias, $field, $operationAlias)
    {
        switch (strtolower($operation)) {
            case "avg":
            case "count":
            case "countdistinct":
            case "groupconcat":
            case "max":
            case "min":
            case "stddev":
            case "stddevpop":
            case "stddevsamp":
            case "sum":
            case "varpop":
            case "varsamp":
                $this->field     = $field;
                $this->modelAlias     = $modelAlias;
                $this->operation = $operation;
                $this->operationAlias = $operationAlias;
                break;

            default:
                throw new QueryBuilderException("Unknown group by operation ($operation)");
        }
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return $this->operationAlias;
    }

    /**
     * @return string
     */
    public function getSql()
    {
        switch (strtolower($this->operation)) {
            case "avg":
                return "AVG(".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
            case "count":
                return "COUNT(".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
            case "countdistinct":
                return "COUNT(DISTINCT ".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
            case "groupconcat":
                return "GROUP_CONCAT(".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
            case "max":
                return "MAX(".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
            case "min":
                return "MIN(".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
            case "stddev":
                return "STDDEV(".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
            case "stddevPop":
                return "STDDEV_POP(".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
            case "stddevSamp":
                return "STDDEV_SAMP(".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
            case "sum":
                return "SUM(".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
            case "varpop":
                return "VAR_POP(".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
            case "varsamp":
                return "VAR_SAMP(".$this->modelAlias.".".$this->field.") AS ".$this->operationAlias;
                break;
        }
    }
}
