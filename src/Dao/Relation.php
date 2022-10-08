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
    /**
     * Contruct relation
     * @param string $daoClass
     * @param array $relationKeys
     * @param Dao $daoFactory
     * @param string $alias
     */
    public function __construct(protected string $daoClass, protected array $keys,
                                protected Dao $daoFactory, protected string $alias)
    {
        $this->daoFactory  = $daoFactory;
        $this->keys        = $relationKeys;
        $this->alias       = $alias;
    }

    /**
     * Get dao
     * @return AbstractDao
     */
    public function getDao(): AbstractDao
    {
        return $this->daoFactory->get($this->daoClass);
    }

    /**
     * Get alias name
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * Get keys
     * @return array
     */
    public function getKeys(): array
    {
        return $this->keys;
    }
}
