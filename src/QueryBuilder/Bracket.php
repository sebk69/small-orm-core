<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\QueryBuilder;

/**
 * Bracket for conditions
 */
class Bracket
{
    public $parent;
    public $conditions = array();
    public $operators  = array();

    /**
     * Construct with parent object
     * @param mixed $parent
     */
    public function __construct($parent)
    {
        $this->parent = $parent;
    }

    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    public function __clone()
    {
        $fromConditions = $this->conditions;
        $this->conditions = array();
        foreach($fromConditions as $condition) {
            $toCondition = clone $condition;
            if($toCondition instanceof Bracket) {
                $toCondition->setParent($this);
            }
            $this->conditions[] = $toCondition;
        }
    }

    /**
     * Add first condition
     * @param \Sebk\SmallOrmCore\QueryBuilder\Condition $condition
     * @return \Sebk\SmallOrmCore\QueryBuilder\Bracket
     * @throws BracketException
     */
    public function firstCondition($var1, $operator, $var2 = null)
    {
        if (isset($this->conditions[0])) {
            throw new BracketException("The first element of bracket has already been defined");
        }

        $this->conditions[0] = new Condition($var1, $operator, $var2);

        return $this;
    }

    /**
     * And operator
     * @param \Sebk\SmallOrmCore\QueryBuilder\Condition $condition
     * @return \Sebk\SmallOrmCore\QueryBuilder\Bracket
     */
    public function andCondition($var1, $operator, $var2 = null)
    {
        $this->operators[]  = "AND";
        $this->conditions[] = new Condition($var1, $operator, $var2);

        return $this;
    }

    /**
     * Or operator
     * @param \Sebk\SmallOrmCore\QueryBuilder\Condition $condition
     * @return \Sebk\SmallOrmCore\QueryBuilder\Bracket
     */
    public function orCondition($var1, $operator, $var2 = null)
    {
        $this->operators[]  = "OR";
        $this->conditions[] = new Condition($var1, $operator, $var2);

        return $this;
    }

    /**
     * Xor operator
     * @param \Sebk\SmallOrmCore\QueryBuilder\Condition $condition
     * @return \Sebk\SmallOrmCore\QueryBuilder\Bracket
     */
    public function xorCondition($var1, $operator, $var2 = null)
    {
        $this->operators[]  = "XOR";
        $this->conditions[] = new Condition($var1, $operator, $var2);

        return $this;
    }

    /**
     * Add first condition as bracket
     * @param \Sebk\SmallOrmCore\QueryBuilder\Bracket $condition
     * @return \Sebk\SmallOrmCore\QueryBuilder\bracket
     * @throws BracketException
     */
    public function firstBracket(Bracket $condition = null)
    {
        if (isset($this->conditions[0])) {
            throw new BracketException("The first element of bracket has already been defined");
        }

        if ($condition === null) {
            $condition = new bracket($this);
        }

        $this->conditions[0] = $condition;

        return $condition;
    }

    /**
     * And operator on bracket
     * @param \Sebk\SmallOrmCore\QueryBuilder\Bracket $condition
     * @return \Sebk\SmallOrmCore\QueryBuilder\Bracket
     */
    public function andBracket(Bracket $condition = null)
    {
        if ($condition === null) {
            $condition = new bracket($this);
        }

        $this->operators[]  = "AND";
        $this->conditions[] = $condition;

        return $condition;
    }

    /**
     * Or operation on bracket
     * @param \Sebk\SmallOrmCore\QueryBuilder\Bracket $condition
     * @return \Sebk\SmallOrmCore\QueryBuilder\Bracket
     */
    public function orBracket(Bracket $condition = null)
    {
        if ($condition === null) {
            $condition = new bracket($this);
        }

        $this->operators[]  = "OR";
        $this->conditions[] = $condition;

        return $condition;
    }

    /**
     * Xor operation on bracket
     * @param \Sebk\SmallOrmCore\QueryBuilder\Bracket $condition
     * @return \Sebk\SmallOrmCore\QueryBuilder\Bracket
     */
    public function xorBracket(Bracket $condition = null)
    {
        if ($condition === null) {
            $condition = new bracket($this);
        }

        $this->operators[]  = "XOR";
        $this->conditions[] = $condition;

        return $condition;
    }

    /**
     * Get sql
     * @return string
     */
    public function getSql()
    {
        $sql = "";

        if ($this->parent instanceof Bracket) {
            $sql .= "(";
        }

        foreach ($this->conditions as $i => $condition) {
            $sql .= $condition->getSql();
            if (isset($this->operators[$i])) {
                $sql .= " ".$this->operators[$i]." ";
            }
        }

        if ($this->parent instanceof Bracket) {
            $sql .= ")";
        }

        return $sql;
    }

    /**
     * End bracket and return parent object for chain
     * @return Bracket
     * @throws BracketException
     */
    public function endBracket()
    {
        if (!$this->parent instanceof Bracket) {
            throw new BracketException("Use end bracket where parent is not bracket");
        }

        return $this->parent;
    }

    /**
     * End where clause and return parent object for chain
     * @return QueryBuilder
     * @throws BracketException
     */
    public function endWhere()
    {
        if (!$this->parent instanceof QueryBuilder) {
            throw new BracketException("Use end where where parent is not query");
        }

        return $this->parent;
    }

    /**
     * End join condition clause and return parent object for chain
     * @return QueryBuilder
     * @throws BracketException
     */
    public function endJoinCondition()
    {
        if (!$this->parent instanceof JoinBuilder) {
            throw new BracketException("Use end join where parent is not join");
        }

        return $this->parent;
    }
}
