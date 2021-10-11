<?php
/**
 * This file is a part of SebkSmallOrmCore
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\QueryBuilder;

use Sebk\SmallOrmCore\QueryBuilder\FromBuilder;

/**
 * Field definition for condition
 */
class OrderByField
{
    public $model;
    public $fieldNameInModel;
    protected $sens;

    /**
     * Construct field definition
     * @param FromBuilder $model
     * @param string $fieldNameInModel
     */
    public function __construct(FromBuilder $model, $fieldNameInModel,
                                $sens = "ASC")
    {
        switch ($sens) {
            case "ASC":
            case "DESC":
                break;

            default:
                throw new QueryBuilderException("Sens of order by can't be '$sens'");
        }

        $this->model            = $model;
        $this->fieldNameInModel = $fieldNameInModel;
        $this->sens             = $sens;
    }
    
    public function __clone()
    {
        $this->model = clone $this->model;
    }

    /**
     *
     * @return string
     */
    public function getSql()
    {
        return $this->model->buildFieldIdentifiedByStringForSql($this->fieldNameInModel, false)." ".$this->sens." ";
    }
}