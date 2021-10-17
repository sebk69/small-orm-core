<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyrightt 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\QueryBuilder;

use Sebk\SmallOrmCore\Dao\AbstractDao;

class DeleteBuilder
{
    protected $baseDao;
    protected $from;
    protected $where;
    protected $parameters = array();

    /**
     * Construct UpdateBuilder
     * @param AbstractDao $baseDao
     * @param string $baseAlias
     */
    public function __construct(AbstractDao $baseDao)
    {
        $this->baseDao = $baseDao;

        $baseAlias = str_replace("`", "", $baseDao->getDbTableName());

        $this->from = new FromBuilder($baseDao, $baseAlias);
    }

    public function __clone() {
        $this->from = clone $this->from;
        $this->where = clone $this->where;
        $this->where->setParent($this);
    }

    /**
     * Create query builder from this
     * @return QueryBuilder
     */
    public function createQueryBuilder()
    {
        $query = $this->baseDao->createQueryBuilder($this->from->getAlias());
        if($this->where !== null) {
            $query->setWhere(clone $this->where);
        }
        foreach ($this->getParameters() as $paramName => $paramValue) {
            $query->setParameter($paramName, $paramName);
        }

        return $query;
    }

    /**
     * Initialize where clause
     * @return Bracket
     */
    public function where()
    {
        $this->where = new Bracket($this);

        return $this->where;
    }

    /**
     * Get condition field object
     * @param string $fieldName
     * @param string $modelAlias
     * @return \Sebk\SmallOrmCore\QueryBuilder\ConditionField
     * @throws QueryBuilderException
     */
    public function getFieldForCondition($fieldName, $modelAlias = null)
    {
        if ($this->from->getAlias() == $modelAlias || $modelAlias === null) {
            if ($this->from->getDao()->hasField($fieldName)) {
                return new ConditionField($this->from, $fieldName);
            }
        }

        throw new QueryBuilderException("Field '$fieldName' is not in model aliased '$modelAlias'");
    }

    /**
     * Get sql for update
     * @return string
     */
    public function getSql()
    {
        $sql = "DELETE FROM ".$this->from->getDao()->getDbTableName()." ";

        if($this->where !== null) {
            $sql .= " WHERE ".$this->where->getSql();
        }

        return $sql;
    }

    /**
     * Set parameter
     * @param string $paramName
     * @param string $value
     * @return \Sebk\SmallOrmCore\QueryBuilder\QueryBuilder
     */
    public function setParameter($paramName, $value)
    {
        $this->parameters[$paramName] = $value;

        return $this;
    }

    /**
     * Get query parameters
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }
}
