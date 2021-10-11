<?php
/**
 * This file is a part of SebkSmallOrmCore
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\QueryBuilder;

use Sebk\SmallOrmCore\Dao\AbstractDao;
use Sebk\SmallOrmCore\Dao\Field;

/**
 * From definition
 */
class FromBuilder
{
    protected $dao;
    protected $alias;

    /**
     * Constructor
     * @param AbstractDao $dao
     * @param string $alias
     */
    public function __construct(AbstractDao $dao = null, $alias)
    {
        $this->dao   = $dao;
        $this->alias = $alias;
    }

    /**
     * Get dao
     * @return AbstractDao
     */
    public function getDao()
    {
        return $this->dao;
    }

    /**
     * Get model alias in query
     * @return string
     */
    public function getAlias($raw = false)
    {
        if($raw) {
            return "`" . $this->alias . "`";
        }

        return $this->alias;
    }

    /**
     * Get field object for sql
     * @param Field $field
     * @return string
     */
    protected function buildFieldForSql(Field $field, $withAlias = true)
    {
        $result = "`".$this->alias."`.".$field->getDbName();
        if($withAlias) {
            $result .= " AS ".$this->getFieldAliasForSql($field);
        }
        
        return $result;
    }

    /**
     * Get field alias for sql
     * @param Field $field
     * @return string
     */
    public function getFieldAliasForSql(Field $field, $escape = true)
    {
        if($escape) {
            return "`" . $this->alias . "_" . $field->getModelName() . "`";
        }

        return $this->alias . "_" . $field->getModelName();
    }

    /**
     * Get field alias for sql
     * @return array
     */
    public function getFieldAliasIdentifiedByStringForSql($fieldNameInModel)
    {
        $field = $this->getDao()->getField($fieldNameInModel);

        return $this->getFieldAliasForSql($field);
    }

    /**
     * Get field identified by string for sql
     * @param string $field
     * @return string
     */
    public function buildFieldIdentifiedByStringForSql($fieldNameInModel, $withAlias = true)
    {
        $field = $this->getDao()->getField($fieldNameInModel);

        return $this->buildFieldForSql($field, $withAlias);
    }

    /**
     * Get fields as array of select part of sql statement
     * @return array
     */
    public function getFieldsForSqlAsArray()
    {
        $fieldsSelection = array();
        foreach ($this->dao->getFields() as $field) {
            $fieldsSelection[] = $this->buildFieldForSql($field);
        }

        return $fieldsSelection;
    }
    
    public function getDbFieldFromModelAlias($modelAlias) {
        $fieldsSelection = array();
        foreach ($this->dao->getFields() as $field) {
            if($field->getModelName() == $modelAlias) {
                return $field->getDbName();
            }
        }

        throw new QueryBuilderException("Field '$modelAlias' don't exists in model aliased '".$this->alias."'");
    }

    /**
     * Get from part for SQL statement
     * @return string
     */
    public function getSql()
    {
        return $this->dao->getDbTableName()." AS `".$this->alias."`";
    }
}