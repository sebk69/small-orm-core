<?php

/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Dao;

use MyProject\Proxies\__CG__\stdClass;
use Sebk\SmallOrmCore\Contracts\QueryBuilderInterface;
use Sebk\SmallOrmCore\Database\AbstractConnection;
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
    protected function addPrimaryKey(string $dbFieldName, string $modelFieldName, mixed $defaultValue = null): AbstractDao {
        throw new \Exception("Primary key is not managed with redis connection");
    }

    /**
     * Create query builder object with base model from this dao
     * @param string|null $alias
     * @return QueryBuilder
     */
    public function createQueryBuilder(string $alias = null): QueryBuilderInterface {
        return new QueryBuilder($this);
    }

    /**
     * Create update builder object to update entries
     * @param string|null $alias
     * @return QueryBuilder
     */
    public function createUpdateBuilder(string $alias = null): QueryBuilder {
        return new QueryBuilder($this);
    }

    /**
     * Create delete builder object to remove entries
     * @param string|null $alias
     * @return QueryBuilder
     */
    public function createDeleteBuilder(string $alias = null): QueryBuilder {
        return new QueryBuilder($this);
    }

    /**
     * Get raw result of a query
     * @param QueryBuilderInterface $query
     * @return array
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     */
    public function getRawResult(QueryBuilderInterface $query): array
    {
        return $this->connection->execute($query->getInstruction(), $query->getParams());
    }

    /**
     * Get result for a query
     * @param QueryBuilderInterface $query
     * @param bool $asCollection
     * @return Model[] | ModelCollection
     */
    public function getResult(QueryBuilderInterface $query, bool $asCollection = true): ModelCollection | array {
        $records = $this->getRawResult($query);

        if ($records == null) {
            return $asCollection ? new ModelCollection() : [];
        }

        $result = [];
        foreach ($records as $record) {
            if ($record != null) {
                if ($record instanceof stdClass) {
                    $model = $this->makeModelFromStdClass($record);
                } elseif (is_array($record)) {
                    $model = $this->makeModelFromStdClass(json_decode(json_encode($record)));
                } else {
                    throw new \Exception("Invalid result");
                }

                if (method_exists($model, "onLoad")) {
                    $model->onLoad();
                }

                $result[] = $model;
            }
        }
        
        if ($asCollection) {
            return $this->newCollection($result);
        }
        
        return $result;
    }

    /**
     * Execute an mass update
     * @param QueryBuilder $query
     * @param bool $executeModelMethods
     * @return AbstractDao
     * @throws \Exception
     */
    public function executeUpdate(QueryBuilderInterface $query, bool $executeModelMethods = true): AbstractDao
    {
        throw new \Exception("No mass update for redis connector !");
    }

    /**
     * Execute a mass delete
     * @param QueryBuilder $query
     * @param bool $executeModelMethods
     * @return AbstractDao|AbstractRedisDao
     * @throws \Exception
     */
    public function executeDelete(QueryBuilderInterface $query, bool $executeModelMethods = true): AbstractRedisDao
    {
        throw new \Exception("No mass delete for redis connector !");
    }

    /**
     * Build result from query
     * @param QueryBuilder $query
     * @param array $records
     * @param string|null $alias
     * @param bool $asCollection
     * @param bool $groupByModels
     * @return array|ModelCollection
     * @throws \Exception
     */
    public function buildResult(QueryBuilderInterface $query, array $records, string $alias = null, bool $asCollection = false, bool $groupByModels = false): ModelCollection | array
    {
        throw new \Exception("buildResult not available for redis connector !");
    }
    
    /**
     * Save a model in redis
     * @param Model $model
     * @param AbstractConnection|null $forceConnection
     * @return $this
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     */
    protected function insert(Model $model, AbstractConnection $forceConnection = null): AbstractRedisDao
    {
        // Create query
        $query = $this->createQueryBuilder()
            ->set($model->getKey(), $model)
        ;
        
        // Execute insert
        $this->connection->execute($query->getInstruction(), $query->getParams(), false, $forceConnection);
        
        return $this;
    }

    /**
     * Alias of insert
     * @param Model $model
     * @param AbstractConnection|null $forceConnection
     * @return $this
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     */
    protected function update(Model $model, AbstractConnection $forceConnection = null): AbstractRedisDao
    {
        return $this->insert($model, $forceConnection);
    }

    /**
     * Delete a model
     * @param Model $model
     * @param AbstractConnection|null $forceConnection
     * @return $this
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     */
    public function delete(Model $model, AbstractConnection $forceConnection = null): AbstractRedisDao
    {
        // Create query
        $query = $this->createQueryBuilder()
            ->del($model->getKey())
        ;

        // Execute beforeDelete method if exists
        if (method_exists($model, "beforeDelete")) {
            $model->beforeDelete();
        }

        // Perform delete
        $this->connection->execute($query->getInstruction(), $query->getParams(), false, $forceConnection);

        // Execute afterDelete method if exists
        if (method_exists($model, "afterDelete")) {
            $model->afterDelete();
        }
        
        return $this;
    }

    /**
     * Find a simple occurence
     * @param string $conds
     * @param array $dependenciesAliases
     * @return Model
     * @throws DaoEmptyException
     */
    public function findOneBy(array $conds = [], array $dependenciesAliases = []): Model
    {
        if (count($conds) > 1) {
            throw new \Exception("Can't use multiples keys for findOneBy");
        }

        $query = $this->createQueryBuilder();
        $query->get($conds[0]);
        
        $result = $this->getResult($query);
        
        if (count($result) == 0) {
            throw new DaoEmptyException("Find one with no result");
        }

        return $result[0];
    }

    /**
     * Find multiple occurences
     * @param array|string $conds
     * @param array $dependenciesAliases
     * @return ModelCollection|array
     * @throws \Exception
     */
    public function findBy(array $conds, array $dependenciesAliases = []): ModelCollection
    {
        $query = $this->createQueryBuilder();

        foreach ($conds as $cond) {
            if (strstr($cond, "*")) {
                $sub = ($this->createQueryBuilder())
                    ->keys($cond);
                foreach ($this->getRawResult($sub) as $key) {
                    $query->get($key, false);
                }
            }


            $query->get($cond);
        }

        return $this->getResult($query);
    }
}