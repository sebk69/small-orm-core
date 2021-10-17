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
class JoinBuilder extends FromBuilder
{
    protected $from;
    protected $relation;
    protected $bracket;
    protected $parent;

    /**
     * @param \Sebk\SmallOrmCore\QueryBuilder\QueryBuilder $parent
     * @return \Sebk\SmallOrmCore\QueryBuilder\JoinBuilder
     */
    public function setParent(QueryBuilder $parent)
    {
        $this->parent = $parent;

        return $this;
    }

    public function __clone()
    {
        $this->from = clone $this->from;
        $this->relation = clone $this->relation;
        $this->bracket = clone $this->bracket;
    }

    /**
     * Set from and build primary conditions of relation
     * @param \Sebk\SmallOrmCore\QueryBuilder\FromBuilder $from
     * @param string $relationAlias
     * @return \Sebk\SmallOrmCore\QueryBuilder\JoinBuilder
     * @throws JoinBuilderException
     */
    public function setFrom(FromBuilder $from, $relationAlias)
    {
        if (!$this->parent instanceof QueryBuilder) {
            throw new JoinBuilderException("Parent must be defined before set from");
        }

        $this->from     = $from;
        $this->relation = $this->from->getDao()->getRelation($relationAlias);
        $this->dao      = $this->relation->getDao();

        return $this;
    }

    /**
     *
     * @return Relation
     */
    public function getDaoRelation()
    {
        return $this->relation;
    }

    /**
     * Build primary keys relation conditions
     * @throws JoinBuilderException
     */
    public function buildBaseConditions()
    {
        if ($this->bracket !== null) {
            throw new JoinBuilderException("Base condition already defined");
        }

        $this->bracket = new Bracket($this);
        $first         = true;
        foreach ($this->relation->getKeys() as $fromField => $toField) {
            if ($first) {
                $this->bracket->firstCondition(
                    $this->parent->getFieldForCondition($fromField,
                        $this->from->getAlias()), "=",
                    $this->parent->getFieldForCondition($toField,
                        $this->getAlias()));
            } else {
                $this->bracket->andCondition(
                    $this->parent->getFieldForCondition($fromField,
                        $this->from->getAlias()), "=",
                    $this->parent->getFieldForCondition($toField,
                        $this->getAlias()));
            }

            $first = false;
        }

        return $this;
    }

    /**
     * Add conditions to relation
     * @return Bracket
     * @throws JoinBuilderException
     */
    public function joinCondition()
    {
        if (!isset($this->bracket)) {
            throw new JoinBuilderException("Join condition without setFrom");
        }

        return $this->bracket;
    }

    /**
     * End join
     * @return QueryBuilder
     */
    public function endJoin()
    {
        return $this->parent;
    }

    /**
     * Get sql code for join
     * @return string
     */
    public function getSql($type = "JOIN")
    {
        $sql = " ".$type." ";

        $sql .= parent::getSql();

        $sql .= " ON ";

        $sql .= $this->bracket->getSql();

        return $sql;
    }

    public function getFromAlias()
    {
        return $this->from->getAlias();
    }
}
