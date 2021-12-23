<?php

/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Dao;

use Sebk\SmallOrmCore\Database\AbstractConnection;
use Sebk\SmallOrmCore\Factory\Dao;
use Sebk\SmallOrmCore\QueryBuilder\QueryBuilder;
use Sebk\SmallOrmCore\QueryBuilder\UpdateBuilder;
use Sebk\SmallOrmCore\QueryBuilder\DeleteBuilder;

/**
 * Abstract class to provide base dao features
 */
abstract class AbstractDao {

    protected $connection;
    protected $daoFactory;
    protected $modelNamespace;
    protected $container;
    private $modelName;
    private $modelBundle;
    private $dbTableName;
    private $primaryKeys = array();
    private $fields = array();
    private $toOne = array();
    private $toMany = array();
    private $defaultValues = array();

    public function __construct(AbstractConnection $connection, Dao $daoFactory, $modelNamespace, $modelName, $modelBundle, $container) {
        $this->connection = $connection;
        $this->daoFactory = $daoFactory;
        $this->modelNamespace = $modelNamespace;
        $this->modelName = $modelName;
        $this->modelBundle = $modelBundle;
        $this->container = $container;

        $this->build();
    }

    /**
     * Get DAO factory
     * @return Dao
     */
    public function getDaoFactory()
    {
        return $this->daoFactory;
    }

    /**
     * Get connection
     * @return AbstractConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get model name
     * @return string
     */
    public function getModelName()
    {
        return $this->modelName;
    }

    /**
     * @return string
     */
    public function getBundle()
    {
        return $this->modelBundle;
    }

    /**
     * @param $name
     * @return $this
     */
    protected function setModelName($name) {
        $this->modelName = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getDbTableName($protected = true) {
        if ($protected) {
            return "`" . $this->dbTableName . "`";
        } else {
            return $this->dbTableName;
        }
    }

    /**
     * @param string $name
     * @param $name
     * @return $this
     */
    protected function setDbTableName($name) {
        $this->dbTableName = $name;

        return $this;
    }

    /**
     * @param string $dbFieldName
     * @param string $modelFieldName
     */
    protected function addPrimaryKey($dbFieldName, $modelFieldName, $defaultValue = null) {
        $this->primaryKeys[] = new Field($dbFieldName, $modelFieldName);
        $this->defaultValues[$modelFieldName] = $defaultValue;

        return $this;
    }

    /**
     * Add a field
     * @param $dbFieldName
     * @param $modelFieldName
     * @param null $defaultValue
     * @param string $type
     * @param null $format
     * @return $this
     * @throws \Exception
     */
    protected function addField($dbFieldName, $modelFieldName, $defaultValue = null, $type = Field::TYPE_STRING, $format = null) {
        $field = new Field($dbFieldName, $modelFieldName);
        $field->setType($type, $format);
        $this->fields[] = $field;
        $this->defaultValues[$modelFieldName] = $defaultValue;

        return $this;
    }

    /**
     * Build definition of table
     * Must be defined in model acheviment
     */
    abstract protected function build();

    /**
     * Get primary keys definition
     * @return array
     */
    public function getPrimaryKeys() {
        return $this->primaryKeys;
    }

    /**
     * Get fields difinitions
     * @param boolean $withIds
     * @return Field[]
     */
    public function getFields($withIds = true) {
        $result = array();
        if ($withIds == true) {
            $result = $this->primaryKeys;
        }

        $result = array_merge($result, $this->fields);

        return $result;
    }

    /**
     * Create new model
     * @return \Sebk\SmallOrmCore\Dao\modelClass
     */
    public function newModel() {
        $modelClass = $this->modelNamespace . "\\" . $this->modelName;

        $primaryKeys = array();
        foreach ($this->primaryKeys as $primaryKey) {
            $primaryKeys[] = lcfirst($primaryKey->getModelName());
        }

        $fields = array();
        $types = [];
        foreach ($this->fields as $field) {
            $fields[] = lcfirst($field->getModelName());
            $types[] = ["type" => $field->getType(), "format" => $field->getFormat()];
        }

        $toOnes = array();
        foreach ($this->toOne as $toOneAlias => $toOne) {
            $toOnes[] = lcFirst($toOneAlias);
        }

        $toManys = array();
        foreach ($this->toMany as $toManyAlias => $toMany) {
            $toManys[] = lcFirst($toManyAlias);
        }

        $model = new $modelClass($this->modelName, $this->modelBundle, $primaryKeys, $fields, $types, $toOnes, $toManys, $this->container);

        foreach ($this->defaultValues as $property => $defaultValue) {
            $method = "raw" . $property;
            $model->$method($defaultValue);
        }

        return $model;
    }

    /**
     * Get default value for a property
     * @param $property
     * @return mixed
     */
    public function getDefaultValue($property)
    {
        if (isset($this->defaultValues[$property])) {
            return $this->defaultValues[$property];
        }

        return null;
    }

    /**
     * Create a new collection of models
     * @param Model || array $array
     * @return ModelCollection
     */
    public function newCollection($array = array()) {
        $modelClass = $this->modelNamespace . "\\" . $this->modelName . "Collection";
        if (class_exists($modelClass)) {
            $collection = new $modelClass($array);
        } else {
            $collection = new ModelCollection($array);
        }

        return $collection;
    }

    /**
     * Create query builder object with base model from this dao
     * @param type $alias
     * @return QueryBuilder
     */
    public function createQueryBuilder($alias = null) {
        return new QueryBuilder($this, $alias);
    }

    /**
     * Create update builder object to update table of this dao
     * @param type $alias
     * @return UpdateBuilder
     */
    public function createUpdateBuilder($alias = null) {
        return new UpdateBuilder($this, $alias);
    }

    /**
     * Create update builder object to update table of this dao
     * @param type $alias
     * @return UpdateBuilder
     */
    public function createDeleteBuilder($alias = null) {
        return new DeleteBuilder($this, $alias);
    }

    /**
     * Execute sql and get raw result
     * @param QueryBuilder $query
     * @return array
     */
    public function getRawResult(QueryBuilder $query) {
        return $this->connection->execute($query->getSql(), $query->getParameters());
    }

    /**
     * Execute raw sql and get result
     * @param $sql
     * @param $parameters
     * @return array
     */
    public function getRawQueryResult($sql, $parameters = null) {
        return $this->connection->execute($sql, $parameters);
    }

    /**
     * Has field
     * @param string $fieldName
     * @return boolean
     */
    public function hasField($fieldName) {
        foreach ($this->getFields() as $field) {
            if ($field->getModelName($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get field object
     * @param string $fieldName
     * @return Field
     * @throws DaoException
     */
    public function getField($fieldName) {
        foreach ($this->getFields() as $field) {
            if ($field->getModelName() == $fieldName) {
                return $field;
            } elseif ($field->getModelName() == ucfirst($fieldName)) {
                return $field;
            }
        }

        throw new DaoException("Field '$fieldName' not found in model '" . $this->modelName . "'");
    }

    /**
     * Add a relation to model
     * @param \Sebk\SmallOrmCore\Dao\Relation $relation
     * @return \Sebk\SmallOrmCore\Dao\AbstractDao
     * @throws DaoException
     */
    public function addRelation(Relation $relation) {
        if ($relation instanceof ToOneRelation) {
            $this->toOne[$relation->getAlias()] = $relation;

            return $this;
        }

        if ($relation instanceof ToManyRelation) {
            $this->toMany[$relation->getAlias()] = $relation;

            return $this;
        }

        throw new DaoException("Unknonw relation type");
    }

    /**
     * Get result for a query
     * @param QueryBuilder $query
     * @param bool $asCollection
     * @return Model[]
     * @throws \Sebk\SmallOrmCore\QueryBuilder\QueryBuilderException
     */
    public function getResult(QueryBuilder $query, $asCollection = false) {
        foreach ($this->primaryKeys as $key) {
            $query->addOrderBy($key->getModelName());
        }

        $records = $this->getRawResult($query);

        if (!$query->isRawSelect()) {
            return $this->buildResult($query, $records, null, $asCollection);
        }

        return $records;
    }

    /**
     * Execute mass update
     * @param UpdateBuilder $query
     * @param bool $executeModelMethods
     * @return $this
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     * @throws \Sebk\SmallOrmCore\QueryBuilder\QueryBuilderException
     */
    public function executeUpdate(UpdateBuilder $query, $executeModelMethods = true) {
        $model = $this->newModel();
        if(!method_exists($model, "beforeSave") && !method_exists($model, "afterSave")) {
            $executeModelMethods = false;
        }

        if (!$executeModelMethods) {
            $this->connection->execute($query->getSql(), $query->getParameters());

            return $this;
        }

        $result = $this->getResult($query->createQueryBuilder($query->getAlias()));
        foreach ($result as $model) {
            foreach ($query->getFieldsToUpdate() as $fieldUpdate) {
                $setter = "raw".$fieldUpdate->getField()->getModelName();
                $model->$setter($fieldUpdate->getUpdateValue());
            }
            $model->persist();
        }

        return $this;
    }

    /**
     * Execute mass delete
     * @param DeleteBuilder $query
     * @param bool $executeModelMethods
     * @return $this
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     * @throws \Sebk\SmallOrmCore\QueryBuilder\QueryBuilderException
     */
    public function executeDelete(DeleteBuilder $query, $executeModelMethods = true) {
        $model = $this->newModel();
        if(!method_exists($model, "beforeDelete") && !method_exists($model, "afterDelete")) {
            $executeModelMethods = false;
        }

        if (!$executeModelMethods) {
            $this->connection->execute($query->getSql(), $query->getParameters());

            return $this;
        }

        $result = $this->getResult($query->createQueryBuilder());
        foreach ($result as $model) {
            $model->delete();
        }

        return $this;
    }

    /**
     * Convert resultset to objects
     * @param QueryBuilder $query
     * @param array $records
     * @param string $alias
     * @return array
     */
    protected function buildResult(QueryBuilder $query, $records, $alias = null, $asCollection = false, $groupByModels = false) {
        if ($alias === null) {
            $alias = $query->getRelation()->getAlias();
        }

        if ($asCollection) {
            $result = $this->newCollection();
        } else {
            $result = array();
        }

        $group = array();
        $savedIds = array();
        foreach ($records as $record) {

            $ids = $this->extractPrimaryKeysOfRecord($query, $alias, $record);

            if ($ids !== null) {
                foreach ($ids as $idName => $idValue) {
                    if (count($group) && count($savedIds) && $savedIds[$idName] != $idValue) {
                        $result[] = $this->populate($query, $alias, $group, $asCollection, $groupByModels);

                        $group = array();
                        break;
                    }
                }
                $group[] = $record;

                $savedIds = $ids;
            } else {
                $savedIds = array();
            }
        }
        if (count($group)) {
            $result[] = $this->populate($query, $alias, $group, $asCollection, $groupByModels);
        }

        return $result;
    }

    /**
     *
     * @param QueryBuilder $query
     * @param string $alias
     * @param array $records
     * @return Model
     */
    protected function populate(QueryBuilder $query, $alias, $records, $asCollection, $groupByModels) {
        $model = $this->newModel();
        $fields = $this->extractFieldsOfRecord($query, $alias, $records[0]);

        foreach ($fields as $property => $value) {
            $method = "raw" . $property;
            $model->$method($value);
        }

        if (!$groupByModels) {
            if ($query->getGroupByAlias() == $alias) {
                $groupByModels = true;
            }
        }

        foreach ($query->getChildRelationsForAlias($alias) as $join) {
            if ($join->getDaoRelation() instanceof ToOneRelation) {
                $method = "raw" . $join->getDaoRelation()->getAlias();
                $toOneObjects = $join->getDaoRelation()->getDao()->buildResult($query, $records, $join->getAlias(), $asCollection, $groupByModels);
                if ((!$asCollection && count($toOneObjects)) || ($asCollection && $toOneObjects->count())) {
                    $model->$method($toOneObjects[0]);
                } else {
                    $model->$method(null);
                }
            }

            if ($join->getDaoRelation() instanceof ToManyRelation && !$groupByModels) {
                $method = "raw" . $join->getDaoRelation()->getAlias();
                $toOneObject = $join->getDaoRelation()->getDao()->buildResult($query, $records, $join->getAlias(), $asCollection, $groupByModels);
                $model->$method($toOneObject);
            }
        }

        $model->setOriginalPrimaryKeys();
        $model->fromDb = true;

        if (method_exists($model, "onLoad")) {
            $model->onLoad();
        }

        return $model;
    }

    /**
     * Load a toOneRelation
     * @param string $alias
     * @param Model $model
     * @return Model
     */
    public function loadToOne($alias, $model, $dependenciesAliases = array()) {
        $relation = $this->toOne[$alias];

        $keys = array();
        foreach ($relation->getKeys() as $keyFrom => $keyTo) {
            $method = "get" . $keyFrom;
            $keys[$keyTo] = $model->$method();
        }

        $results = $relation->getDao()->findBy($keys, $dependenciesAliases);

        if (count($results) == 1) {
            $method = "raw" . $alias;
            $model->$method($results[0]);
        }
    }

    /**
     * Load a toManyRelation
     * @param string $alias
     * @param Model $model
     * @return Model
     */
    public function loadToMany($alias, $model, $dependenciesAliases = array()) {
        $relation = $this->toMany[$alias];

        $keys = array();
        foreach ($relation->getKeys() as $keyFrom => $keyTo) {
            $method = "get" . $keyFrom;
            $keys[$keyTo] = $model->$method();
        }

        $method = "raw" . $alias;
        $model->$method($relation->getDao()->findBy($keys, $dependenciesAliases));
    }

    /**
     * Extract ids of this model of record
     * @param QueryBuilder $query
     * @param string $alias
     * @param array $record
     * @return array
     * @throws DaoException
     */
    private function extractPrimaryKeysOfRecord(QueryBuilder $query, $alias, $record) {
        $queryRelation = $query->getRelation($alias);

        $result = array();
        $empty = true;
        foreach ($this->getPrimaryKeys() as $field) {
            $fieldAlias = $queryRelation->getFieldAliasForSql($field, false);
            if ($record[$fieldAlias]) {
                if (isset($record[$fieldAlias]) != null) {
                    $empty = false;
                }
                $result[$field->getModelName()] = $record[$fieldAlias];
            } else {
                throw new DaoException("Record not match query");
            }
        }

        if ($empty) {
            return null;
        }

        return $result;
    }

    /**
     * Extract fields of this model of record
     * @param QueryBuilder $query
     * @param string $alias
     * @param array $record
     * @return array
     * @throws DaoException
     */
    private function extractFieldsOfRecord(QueryBuilder $query, $alias, $record) {
        $queryRelation = $query->getRelation($alias);

        $result = array();
        foreach ($this->getFields() as $field) {
            $fieldAlias = $queryRelation->getFieldAliasForSql($field, false);
            if (array_key_exists($fieldAlias, $record)) {
                $result[$field->getModelName()] = $record[$fieldAlias];
            } else {
                throw new DaoException("Record not match query");
            }
        }

        if ($alias == $query->getGroupByAlias()) {
            foreach ($query->getGroupByOperations() as $operation) {
                if (array_key_exists($operation->getAlias(), $record)) {
                    $result[$operation->getAlias()] = $record[$operation->getAlias()];
                }
            }
        }

        return $result;
    }

    /**
     * Add to one relation
     * @param array $keys
     * @param string $toModel
     * @param string $toBundle
     * @return \Sebk\SmallOrmCore\Dao\AbstractDao
     * @throws DaoException
     */
    public function addToOne($alias, $keys, $toModel, $toBundle = null) {
        if ($toBundle === null) {
            $toBundle = $this->modelBundle;
        }

        foreach ($keys as $thisKey => $otherKey) {
            try {
                $this->getField($thisKey);
            } catch (DaoException $e) {
                throw new DaoException("The field '$thisKey' of relation to '$toModel' of bundle '$toBundle' does not exists in '$this->modelName'");
            }
        }

        $this->toOne[$alias] = new ToOneRelation($toBundle, $toModel, $keys, $this->daoFactory, $alias);

        return $this;
    }

    /**
     * Return to one relations
     * @return ToOneRelation[]
     */
    public function getToOneRelations()
    {
        return $this->toOne;
    }

    /**
     * Add a to many relation
     * @param array $keys
     * @param string $toModel
     * @param string $toBundle
     * @return \Sebk\SmallOrmCore\Dao\AbstractDao
     * @throws DaoException
     */
    public function addToMany($alias, $keys, $toModel, $toBundle = null) {
        if ($toBundle === null) {
            $toBundle = $this->modelBundle;
        }

        foreach ($keys as $thisKey => $otherKey) {
            try {
                $this->getField($thisKey);
            } catch (DaoException $e) {
                throw new DaoException("The field '$thisKey' of relation to '$toModel' of bundle '$toBundle' does not exists in '$this->modelName'");
            }
        }

        $this->toMany[$alias] = new ToManyRelation($toBundle, $toModel, $keys, $this->daoFactory, $alias);

        return $this;
    }

    /**
     * Get to many relations
     * @return ToManyRelation[]
     */
    public function getToManyRelations()
    {
        return $this->toMany;
    }

    /**
     * Get relation
     * @param string $alias
     * @return Relation
     * @throws DaoException
     */
    public function getRelation($alias) {
        if (array_key_exists($alias, $this->toOne)) {
            return $this->toOne[$alias];
        }

        if (array_key_exists($alias, $this->toMany)) {
            return $this->toMany[$alias];
        }

        throw new DaoException("Relation '$alias' does not exists in '$this->modelName' of bundle '$this->modelBundle'");
    }

    /**
     * Insert model in database
     * @param \Sebk\SmallOrmCore\Dao\Model $model
     * @return \Sebk\SmallOrmCore\Dao\AbstractDao
     */
    protected function insert(Model $model) {
        $connection = $this->connection->connect();

        list($sql, $params) = $this->getInsertSql($model);
        
        $lastInsertId = $this->connection->execute($sql, $params, false, $connection);

        if ($lastInsertId == null) {
            if ($this->connection->lastInsertId($connection) !== null) {
                foreach ($model->getPrimaryKeys() as $key => $value) {
                    if ($value === null) {
                        $method = "raw" . $key;
                        $model->$method($this->connection->lastInsertId($connection));
                    }
                }
            }
        } else {
            foreach ($model->getPrimaryKeys() as $key => $value) {
                if ($value === null) {
                    $method = "raw" . $key;
                    $model->$method($lastInsertId);
                }
            }
        }

        $model->fromDb = true;
        $model->altered = false;

        return $this;
    }

    /**
     * Get sql for insert a model in database
     * @param Model $model
     * @param $paramPrefix
     * @return array
     * @throws DaoException
     */
    public function getInsertSql(Model $model, $paramPrefix = "")
    {
        $sql = "INSERT INTO " . $this->connection->getDatabase() . "." . $this->dbTableName . " ";
        $fields = $model->toArray(false, true);

        $columns = array();
        foreach ($fields as $key => $val) {
            $queryFields[$key] = ":" . $paramPrefix . $key;
            $columns[] = $this->getField($key)->getDbName();
            $params[$paramPrefix . $key] = $val;
        }
        $sql .= "(" . implode(", ", $columns) . ")";
        $sql .= " VALUES(";
        $sql .= implode(", ", $queryFields);
        $sql .= ");";
        
        return [$sql, $params];
    }

    /**
     * Insert a record
     * @param string $modelName
     * @return string
     * @throws DaoException
     */
    public function getDbNameFromModelName($modelName) {
        foreach ($this->getFields() as $field) {
            if (strtolower($modelName) == strtolower($field->getModelName())) {
                return $field->getDbName();
            }
        }

        throw new DaoException("Field '$modelName' does not exists in '$this->modelBundle' '$this->modelName' model");
    }

    /**
     * Update a record
     * @param \Sebk\SmallOrmCore\Dao\Model $model
     * @return \Sebk\SmallOrmCore\Dao\AbstractDao
     * @throws DaoException
     */
    protected function update(Model $model, $forceConnection = null) {
        if (!$model->fromDb) {
            throw new DaoException("Try update a record not from db from '$this->modelBundle' '$this->modelName' model");
        }

        list($sql, $parms) = $this->getUpdateSql($model);

        $this->connection->execute($sql, $parms, false, $forceConnection);

        $model->fromDb = true;
        $model->altered = false;

        return $this;
    }

    /**
     * Reuturn sql statement to execute for update model
     * @param Model $model
     * @return array
     * @throws DaoException
     */
    public function getUpdateSql(Model $model, $paramPrefix = "")
    {
        $parms = array();
        
        $sql = "UPDATE " . $this->connection->getDatabase() . "." . $this->dbTableName . " set ";
        $fields = $model->toArray(false, true);

        foreach ($fields as $key => $val) {
            if($val !== Model::FIELD_NOT_PERSIST) {
                $queryFields[$key] = $this->getDbNameFromModelName($key) . " = :" . $paramPrefix . $key;
                $parms[$paramPrefix . $key] = $val;
            }
        }
        $sql .= implode(", ", $queryFields);

        if ($model->getOriginalPrimaryKeys() === null) {
            $model->setOriginalPrimaryKeys();
        }

        $sql .= " WHERE ";
        $conds = array();
        foreach ($model->getOriginalPrimaryKeys() as $originalPk => $originalValue) {
            $conds[] = $this->getDbNameFromModelName($originalPk) . " = :" . $paramPrefix . $originalPk . "OriginalPk";
            $parms[$paramPrefix . $originalPk . "OriginalPk"] = $originalValue;
        }
        $sql .= implode(" AND ", $conds);
        
        $sql .= ";";
        
        return [$sql, $parms];
    }

    /**
     * Delete a record
     * @param \Sebk\SmallOrmCore\Dao\Model $model
     * @return \Sebk\SmallOrmCore\Dao\AbstractDao
     * @throws DaoException
     */
    public function delete(Model $model, $forceConnection = null) {
        if (!$model->fromDb) {
            throw new DaoException("Try delete a record not from db from '$this->modelBundle' '$this->modelName' model");
        }

        list($sql, $parms) = $this->getDeleteSql($model);

        if (method_exists($model, "beforeDelete")) {
            $model->beforeDelete();
        }

        $this->connection->execute($sql, $parms, false, $forceConnection);

        if (method_exists($model, "afterDelete")) {
            $model->afterDelete();
        }

        $model->fromDb = true;
        $model->altered = false;

        return $this;
    }

    /**
     * Get sql for deleting a model
     * @param Model $model
     * @param $paramsPrefix
     * @return array
     * @throws DaoException
     */
    public function getDeleteSql(Model $model, $paramsPrefix = "")
    {
        $parms = array();

        $sql = "DELETE FROM " . $this->connection->getDatabase() . "." . $this->dbTableName . " ";

        if ($model->getOriginalPrimaryKeys() === null) {
            $model->setOriginalPrimaryKeys();
        }

        $sql .= " WHERE ";
        $conds = array();
        foreach ($model->getOriginalPrimaryKeys() as $originalPk => $originalValue) {
            $conds[] = $this->getDbNameFromModelName($originalPk) . " = :" . $paramsPrefix . $originalPk . "OriginalPk";
            $parms[$paramsPrefix . $originalPk . "OriginalPk"] = $originalValue;
        }
        $sql .= implode(" AND ", $conds) . ";";
        
        return [$sql, $parms];
    }

    /**
     * Persist a record
     * @param Model $model
     * @param mixed $forceConnection
     * @throws DaoException
     */
    public function persist(Model $model, $forceConnection = null) {
        if (method_exists($model, "beforeSave")) {
            $model->beforeSave();
        }

        if ($model->fromDb) {
            $this->update($model, $forceConnection);
        } else {
            $this->insert($model, $forceConnection);
        }

        if (method_exists($model, "afterSave")) {
            $model->afterSave();
        }
    }

    /**
     *
     * @param stdClass $stdClass
     * @param boolean $setOriginalKeys
     * @return \Sebk\SmallOrmCore\Dao\Model
     */
    public function makeModelFromStdClass($stdClass, $setOriginalKeys = false) {
        $model = $this->newModel();

        foreach ($stdClass as $prop => $value) {
            $method = "raw" . $prop;
            if (!is_object($value) && !is_array($value)) {
                try {
                    $model->$method($value);
                } catch (ModelException $e) {

                }
            } else {
                try {
                    $relation = $this->getRelation($prop);
                    if ($relation instanceof ToOneRelation) {
                        $model->$method($relation->getDao()->makeModelFromStdClass($value));
                    } elseif ($relation instanceof ToManyRelation) {
                        $objects = array();
                        foreach ($value as $key => $modelStdClass) {
                            $objects[$key] = $relation->getDao()->makeModelFromStdClass($modelStdClass);
                        }
                        $model->$method($objects);
                    }
                } catch (DaoException $e) {
                    $model->$method($value);
                }
            }
        }

        if ($setOriginalKeys) {
            $model->setOriginalPrimaryKeys();
        }

        if(isset($stdClass->backup)) {
            $model->setBackup($stdClass->backup);
        }

        if (isset($stdClass->fromDb)) {
            $model->fromDb = $stdClass->fromDb;
        } else {
            $model->fromDb = false;
        }

        $model->altered = true;

        return $model;
    }

    /**
     *
     * @param array $conds
     * @return array
     */
    public function findBy($conds, $dependenciesAliases = array()) {
        $query = $this->createQueryBuilder(lcfirst($this->modelName));

        foreach ($dependenciesAliases as $dependance) {
            foreach ($dependance as $aliasFrom => $aliasTo) {
                $query->leftJoin($aliasFrom, $aliasTo);
            }
        }

        $where = $query->where();

        $first = true;
        foreach ($conds as $field => $value) {
            if ($first) {
                if($value !== null) {
                    $where->firstCondition($query->getFieldForCondition($field), "=", ":" . $field);
                    $query->setParameter($field, $value);
                } else {
                    $where->firstCondition($query->getFieldForCondition($field), "is", null);
                }
            } else {
                if($value !== null) {
                    $where->andCondition($query->getFieldForCondition($field), "=", ":" . $field);
                    $query->setParameter($field, $value);
                } else {
                    $where->andCondition($query->getFieldForCondition($field), "is", null);
                }
            }

            $first = false;
        }

        return $this->getResult($query);
    }

    /**
     * @param $conds
     * @param array $dependenciesAliases
     * @return Model
     * @throws DaoEmptyException
     * @throws DaoException
     */
    public function findOneBy($conds, $dependenciesAliases = array()) {
        $results = $this->findBy($conds, $dependenciesAliases);

        if (count($results) == 0) {
            throw new DaoEmptyException("Find one with no result");
        }

        if (count($results) > 1) {
            throw new DaoException("Find one with multiple result");
        }

        return $results[0];
    }

}
