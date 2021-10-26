<?php

/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Dao;

use Sebk\SmallOrmCore\RedisQueryBuilder\QueryBuilder;

abstract class AbstractRedisDao extends AbstractDao
{
    /**
     * Not managed by redis
     * @param $dbFieldName
     * @param $modelFieldName
     * @param null $defaultValue
     * @throws \Exception
     */
    protected function addPrimaryKey($dbFieldName, $modelFieldName, $defaultValue = null) {
        throw new \Exception("Primary key is not managed with redis connection");
    }

    /**
     * Create query builder object with base model from this dao
     * @return QueryBuilder
     */
    public function createQueryBuilder($alias = null) {
        return new QueryBuilder($this);
    }

    /**
     * Create update builder object to update entries
     * @return QueryBuilder
     */
    public function createUpdateBuilder($alias = null) {
        return new QueryBuilder($this);
    }

    /**
     * Create delete builder object to remove entries
     * @return QueryBuilder
     */
    public function createDeleteBuilder($alias = null) {
        return new QueryBuilder($this);
    }

    /**
     * Get raw result of a query
     * @param QueryBuilder $query
     * @return array|mixed
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     */
    public function getRawResult($query)
    {
        return $this->connection->execute($query->getInstruction(), $query->getParams());
    }

    /**
     * Get result for a query
     * @param QueryBuilder $query
     * @param bool $asCollection
     * @return Model[]
     */
    public function getResult($query, $asCollection = false) {
        $records = $this->getRawResult($query);

        if ($records == null) {
            return null;
        }

        $result = [];
        foreach ($records as $record) {
            $model = $this->makeModelFromStdClass($record);

            if (method_exists($model, "onLoad")) {
                $model->onLoad();
            }

            $result[] = $model;
        }
        
        if ($asCollection) {
            return $this->newCollection($result);
        }
        
        return $result;
    }

    public function executeUpdate($query, $executeModelMethods = true)
    {
        throw new \Exception("No mass update for redis connector !");
    }

    public function executeDelete($query, $executeModelMethods = true)
    {
        throw new \Exception("No mass delete for redis connector !");
    }

    public function buildResult($query, $records, $alias = null, $asCollection = false, $groupByModels = false)
    {
        throw new \Exception("buildResult not available for redis connector !");
    }
    
    public function loadToOne($alias, $model, $dependenciesAliases = array())
    {
        throw new \Exception("loadToOne not available for redis connector !");
    }
    
    public function loadToMany($alias, $model, $dependenciesAliases = array())
    {
        throw new \Exception("loadToMany not available for redis connector !");
    }

    /**
     * Save a model in redis
     * @param Model $model
     * @return $this|AbstractRedisDao
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     */
    protected function insert(Model $model)
    {
        // Create query
        $query = $this->createQueryBuilder()
            ->set($model->getKey(), $model)
        ;
        
        // Execute insert
        $this->connection->execute($query->getInstruction(), $query->getParams());
        
        return $this;
    }

    /**
     * Alias of insert
     * @param Model $model
     * @return $this|AbstractRedisDao
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     */
    protected function update(Model $model)
    {
        return $this->insert($model);
    }

    /**
     * Delete a model
     * @param Model $model
     * @return $this|AbstractRedisDao
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     */
    public function delete(Model $model)
    {
        // Create query
        $query == $this->createQueryBuilder()
            ->del($model->getKey())
        ;

        // Execute beforeDelete method if exists
        if (method_exists($model, "beforeDelete")) {
            $model->beforeDelete();
        }

        // Perform delete
        $this->connection->execute($query->getInstruction(), $query->getParams());

        // Execute afterDelete method if exists
        if (method_exists($model, "afterDelete")) {
            $model->afterDelete();
        }
        
        return $this;
    }

    /**
     * Find a simple occurence
     * @param $conds
     * @param array $dependenciesAliases
     * @return Model|Model[]
     * @throws \Exception
     */
    public function findOneBy($conds, $dependenciesAliases = array())
    {
        if (is_array($conds)) {
            throw new \Exception("Can't use multiples keys for findOneBy");
        }
        
        $query = $this->createQueryBuilder();
        $query->get($conds);
        
        $result = $this->getResult($query);
        
        if (count($result) == 0) {
            throw new DaoEmptyException("Find one with no result");
        }

        return $result;
    }

    /**
     * Find multiple occurences
     * @param array $conds
     * @param array $dependenciesAliases
     * @return Model[]
     * @throws \Exception
     */
    public function findBy($conds, $dependenciesAliases = array())
    {
        $query = $this->createQueryBuilder();

        if (is_array($conds)) {
            foreach ($conds as $cond) {
                $query->get($cond);
            }
        } else {
            $query->get($conds);
        }
        
        return $this->getRawResult($query);
    }
}