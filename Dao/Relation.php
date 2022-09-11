<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyrightt 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Dao;

use Sebk\SmallOrmCore\Factory\Dao;

/**
 * Relation to another model
 */
class Relation
{
    protected $daoClass;
    protected $keys;
    protected $daoFactory;
    protected $alias;

    /**
     * Contruct relation
     * @param $dao
     * @param $relationKeys
     * @param Dao $daoFactory
     * @param $alias
     */
    public function __construct(string $daoClass, array $relationKeys,
                                Dao $daoFactory, string $alias)
    {
        $this->daoFactory  = $daoFactory;
        $this->keys        = $relationKeys;
        $this->alias       = $alias;
    }

    /**
     * @return AbstractDao
     */
    public function getDao()
    {
        return $this->daoFactory->get($this->daoClass);
    }

    /**
     *
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @return array
     */
    public function getKeys()
    {
        return $this->keys;
    }
}
