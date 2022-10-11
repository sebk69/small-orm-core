<?php

/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Dao;

use Psr\Container\ContainerInterface;
use Sebk\SmallOrmCore\Contracts\QueryBuilderInterface;
use Sebk\SmallOrmCore\Database\AbstractConnection;
use Sebk\SmallOrmCore\Factory\Connections;
use Sebk\SmallOrmCore\Factory\Dao;
use Sebk\SmallOrmCore\QueryBuilder\QueryBuilder;
use Sebk\SmallOrmCore\QueryBuilder\UpdateBuilder;
use Sebk\SmallOrmCore\QueryBuilder\DeleteBuilder;
use Sebk\SmallOrmCore\Validator\AbstractValidator;

/**
 * Abstract class to provide base dao features
 */
abstract class AbstractDao {

    protected AbstractConnection $connection;
    protected string $connectionName = "default";

    protected Dao $daoFactory;
    protected ContainerInterface $container;
    protected string | null $validatorClass = null;
    private string $modelClass;
    private string | null $collectionClass = null;
    private string $dbTableName;
    /** @var Field[] */
    private array $primaryKeys = [];
    /** @var Field[] */
    private $fields = [];
    /** @var ToOneRelation[] */
    private array $toOne = [];
    /** @var ToManyRelation[] */
    private array $toMany = [];
    private array $defaultValues = [];

    public function __construct(Connections $connections, Dao $daoFactory, $container) {
        $this->daoFactory = $daoFactory;
        $this->container = $container;

        $this->build();

        $this->connection = $connections->get($this->connectionName);
    }

    /**
     * Get DAO factory
     * @return Dao
     */
    public function getDaoFactory(): Dao
    {
        return $this->daoFactory;
    }

    /**
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * @param string $connectionName
     * @return $this
     */
    public function setConnectionName(string $connectionName): AbstractDao
    {
        $this->connectionName = $connectionName;
        
        return $this;
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
     * @param AbstractConnection $connection
     * @return $this
     */
    public function setConnection(AbstractConnection $connection): AbstractDao
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Get model name
     * @return string
     */
    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * @param string $modelClass
     * @return $this
     */
    public function setModelClass(string $modelClass): AbstractDao
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    /**
     * Get the collection class name
     * @return string|null
     */
    public function getCollectionClass(): string|null
    {
        return $this->collectionClass;
    }

    /**
     * Set collection class
     * @param string|null $collectionClass
     * @return AbstractConnection
     */
    public function setCollectionClass(string $collectionClass = null): AbstractConnection
    {
        $this->collectionClass = $collectionClass;

        return $this;
    }

    /**
     * Get table name from db
     * If protected return `name` instead of name
     * @param bool $protected
     * @return string
     */
    public function getDbTableName(bool $protected = true): string {
        if ($protected) {
            return "`" . $this->dbTableName . "`";
        } else {
            return $this->dbTableName;
        }
    }

    /**
     * @param string $name
     * @param string $name
     * @return $this
     */
    protected function setDbTableName(string $name): AbstractDao {
        $this->dbTableName = $name;

        return $this;
    }

    /**
     * @param string $dbFieldName
     * @param string $modelFieldName
     * @param mixed|null $defaultValue
     * @return $this
     */
    protected function addPrimaryKey(string $dbFieldName, string $modelFieldName, mixed $defaultValue = null): AbstractDao {
        $this->primaryKeys[] = new Field($dbFieldName, $modelFieldName);
        $this->defaultValues[$modelFieldName] = $defaultValue;

        return $this;
    }

    /**
     * Add a field
     * @param string $dbFieldName
     * @param string $modelFieldName
     * @param mixed|null $defaultValue
     * @param string $type
     * @param mixed|null $format
     * @return $this
     * @throws \Exception
     */
    protected function addField(string $dbFieldName, string $modelFieldName, mixed $defaultValue = null, string $type = Field::TYPE_STRING, mixed $format = null) {
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
     * @return Field[]
     */
    public function getPrimaryKeys(): array {
        return $this->primaryKeys;
    }

    /**
     * Get fields difinitions
     * @param boolean $withIds
     * @return Field[]
     */
    public function getFields(bool $withIds = true): array {
        $result = array();
        if ($withIds == true) {
            $result = $this->primaryKeys;
        }

        $result = array_merge($result, $this->fields);

        return $result;
    }

    /**
     * Create new model
     * @return Model
     */
    public function newModel(): Model {
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

        $modelClass = $this->getModelClass();
        $model = new $modelClass($primaryKeys, $fields, $types, $toOnes, $toManys, $this->container, $this);

        foreach ($this->defaultValues as $property => $defaultValue) {
            $method = "raw" . $property;
            $model->$method($defaultValue);
        }

        return $model;
    }

    /**
     * Get default value for a property
     * @param string $property
     * @return mixed
     */
    public function getDefaultValue(string $property): mixed
    {
        if (isset($this->defaultValues[$property])) {
            return $this->defaultValues[$property];
        }

        return null;
    }

    /**
     * Create a new collection of models
     * @param Model | Model[] $array
     * @return ModelCollection
     */
    public function newCollection(Model | array $array = []): ModelCollection {
        $collectionClass = $this->getCollectionClass();
        if (class_exists($collectionClass)) {
            $collection = new $collectionClass($array);
        } else {
            $collection = new ModelCollection($array);
        }

        return $collection;
    }

    /**
     * Create query builder object with base model from this dao
     * @param string | null $alias
     * @return QueryBuilder
     */
    public function createQueryBuilder(string $alias = null): QueryBuilderInterface {
        return new QueryBuilder($this, $alias);
    }

    /**
     * Create update builder object to update table of this dao
     * @param string | null $alias
     * @return UpdateBuilder
     */
    public function createUpdateBuilder(string $alias = null) {
        return new UpdateBuilder($this, $alias);
    }

    /**
     * Create update builder object to update table of this dao
     * @param string | null $alias
     * @return UpdateBuilder
     */
    public function createDeleteBuilder(string $alias = null) {
        return new DeleteBuilder($this, $alias);
    }

    /**
     * Execute sql and get raw result
     * @param QueryBuilder $query
     * @return array
     */
    public function getRawResult(QueryBuilderInterface $query): array {
        return $this->connection->execute($query->getSql(), $query->getParameters());
    }

    /**
     * Execute raw sql and get result
     * @param string $sql
     * @param array | null $parameters
     * @return array
     */
    public function getRawQueryResult(string $sql, array $parameters = null) {
        return $this->connection->execute($sql, $parameters);
    }

    /**
     * Has field
     * @param string $fieldName
     * @return boolean
     */
    public function hasField(string $fieldName): bool {
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
    public function getField($fieldName): Field {
        foreach ($this->getFields() as $field) {
            if ($field->getModelName() == $fieldName) {
                return $field;
            } elseif ($field->getModelName() == ucfirst($fieldName)) {
                return $field;
            }
        }

        throw new DaoException("Field '$fieldName' not found in model (" . $this->modelClass . ")");
    }

    /**
     * Add a relation to model
     * @param Relation $relation
     * @return AbstractDao
     * @throws DaoException
     */
    public function addRelation(Relation $relation): AbstractDao {
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
     * @return Model[] | ModelCollection
     * @throws \Sebk\SmallOrmCore\QueryBuilder\QueryBuilderException
     */
    public function getResult(QueryBuilderInterface $query, bool $asCollection = true): array | ModelCollection {
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
    public function executeUpdate(QueryBuilderInterface $query, bool $executeModelMethods = true): AbstractDao {
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
    public function executeDelete(QueryBuilderInterface $query, bool $executeModelMethods = true): AbstractDao {
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
    protected function buildResult(QueryBuilderInterface $query, array $records, string $alias = null, bool $asCollection = true, bool $groupByModels = false): array | ModelCollection {
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
     * Populate a model from query and query result
     * @param QueryBuilder $query
     * @param string $alias
     * @param array $records
     * @param bool $asCollection
     * @param bool $groupByModels
     * @return Model
     * @throws DaoException
     */
    protected function populate(QueryBuilder $query, string $alias, array $records, bool $asCollection, bool $groupByModels): Model {
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
     * @param array $dependenciesAliases
     * @return $this
     * @throws DaoEmptyException
     */
    public function loadToOne(string $alias, Model $model, array $dependenciesAliases = array()): AbstractDao {
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
        } else if (count($results) > 1) {
            throw new DaoEmptyException("Multiple result for a load to one ($alias)");
        }

        return $this;
    }

    /**
     * Load a toManyRelation
     * @param string $alias
     * @param Model $model
     * @param array $dependenciesAliases
     * @return AbstractDao
     */
    public function loadToMany(string $alias, Model $model, array $dependenciesAliases = array()): AbstractDao {
        $relation = $this->toMany[$alias];

        $keys = array();
        foreach ($relation->getKeys() as $keyFrom => $keyTo) {
            $method = "get" . $keyFrom;
            $keys[$keyTo] = $model->$method();
        }

        $method = "raw" . $alias;
        $model->$method($relation->getDao()->findBy($keys, $dependenciesAliases));

        return $this;
    }

    /**
     * Extract ids of this model of record
     * @param QueryBuilder $query
     * @param string $alias
     * @param array $record
     * @return array|null
     * @throws \Sebk\SmallOrmCore\QueryBuilder\QueryBuilderException
     */
    private function extractPrimaryKeysOfRecord(QueryBuilder $query, string $alias, array $record): array | null {
        $queryRelation = $query->getRelation($alias);

        $result = array();
        $empty = true;
        foreach ($this->getPrimaryKeys() as $field) {
            $fieldAlias = $queryRelation->getFieldAliasForSql($field, false);
            if (isset($record[$fieldAlias])) {
                $result[$field->getModelName()] = $record[$fieldAlias];
                $empty = false;
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
     * @throws \Sebk\SmallOrmCore\QueryBuilder\QueryBuilderException
     */
    private function extractFieldsOfRecord(QueryBuilder $query, string $alias, array $record): array {
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
     * @param string $alias
     * @param array $keys
     * @param string $toModel
     * @return $this
     * @throws DaoException
     */
    public function addToOne(string $alias, array $keys, string $toModel): AbstractDao {
        foreach ($keys as $thisKey => $otherKey) {
            try {
                $this->getField($thisKey);
            } catch (DaoException $e) {
                throw new DaoException("The field '$thisKey' of relation to '$toModel' does not exists in ($this->modelClass'");
            }
        }

        $this->toOne[$alias] = new ToOneRelation($toModel, $keys, $this->daoFactory, $alias);

        return $this;
    }

    /**
     * Return to one relations
     * @return ToOneRelation[]
     */
    public function getToOneRelations(): array
    {
        return $this->toOne;
    }

    /**
     * Add a to many relation
     * @param string $alias
     * @param array $keys
     * @param string $toModel
     * @return $this
     * @throws DaoException
     */
    public function addToMany(string $alias, array $keys, string $toModel): AbstractDao {
        foreach ($keys as $thisKey => $otherKey) {
            try {
                $this->getField($thisKey);
            } catch (DaoException $e) {
                throw new DaoException("The field '$thisKey' of relation to '$toModel' does not exists in '$this->modelClass'");
            }
        }

        $this->toMany[$alias] = new ToManyRelation($toModel, $keys, $this->daoFactory, $alias);

        return $this;
    }

    /**
     * Get to many relations
     * @return ToManyRelation[]
     */
    public function getToManyRelations(): array
    {
        return $this->toMany;
    }

    /**
     * Get relation
     * @param string $alias
     * @return Relation
     * @throws DaoException
     */
    public function getRelation(string $alias): Relation {
        if (array_key_exists($alias, $this->toOne)) {
            return $this->toOne[$alias];
        }

        if (array_key_exists($alias, $this->toMany)) {
            return $this->toMany[$alias];
        }

        throw new DaoException("Relation '$alias' does not exists in '$this->modelClass'");
    }

    /**
     * Insert model in database
     * @param Model $model
     * @return $this
     * @throws DaoException
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     */
    protected function insert(Model $model): AbstractDao {
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
     * Offset 0 is sql request
     * Offset 1 is array of parameters
     * @param Model $model
     * @param string $paramPrefix
     * @return array
     * @throws DaoException
     */
    public function getInsertSql(Model $model, string $paramPrefix = ""): array
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
    public function getDbNameFromModelName(string $modelName): string {
        foreach ($this->getFields() as $field) {
            if (strtolower($modelName) == strtolower($field->getModelName())) {
                return $field->getDbName();
            }
        }

        throw new DaoException("Field '$modelName' does not exists in '$this->modelClass' model");
    }

    /**
     * Update a record
     * @param Model $model
     * @param AbstractConnection | null $forceConnection
     * @return $this
     * @throws DaoException
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     */
    protected function update(Model $model, AbstractConnection $forceConnection = null): AbstractDao {
        if (!$model->fromDb) {
            throw new DaoException("Try update a record not from db from '$this->modelClass' model");
        }

        list($sql, $parms) = $this->getUpdateSql($model);

        $this->connection->execute($sql, $parms, false, $forceConnection);

        $model->fromDb = true;
        $model->altered = false;

        return $this;
    }

    /**
     * Return sql statement to execute for update model
     * Offset 0 is sql request
     * Offset 1 is array of parameters
     * @param Model $model
     * @param string $paramPrefix
     * @return array
     * @throws DaoException
     */
    public function getUpdateSql(Model $model, string $paramPrefix = ""): array
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
     * @param Model $model
     * @param AbstractConnection|null $forceConnection
     * @return $this
     * @throws DaoException
     * @throws \Sebk\SmallOrmCore\Database\ConnectionException
     */
    public function delete(Model $model, AbstractConnection $forceConnection = null) {
        if (!$model->fromDb) {
            throw new DaoException("Try delete a record not from db from '$this->modelClass' model");
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
     * Offset 0 is sql request
     * Offset 1 is array of parameters
     * @param Model $model
     * @param string $paramsPrefix
     * @return array
     * @throws DaoException
     */
    public function getDeleteSql(Model $model, string $paramsPrefix = ""): array
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
     * @param AbstractConnection|null $forceConnection
     * @throws DaoException
     */
    public function persist(Model $model, AbstractConnection $forceConnection = null): AbstractDao {
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

        return $this;
    }

    /**
     * Make new model from an stdClass
     * @param \stdClass $stdClass
     * @param bool $setOriginalKeys
     * @return Model
     * @throws ModelException
     */
    public function makeModelFromStdClass(\stdClass $stdClass, bool $setOriginalKeys = false): Model {
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
     * Find a list of models fron simple conditions
     * @param array $conds
     * @param array $dependenciesAliases
     * @return ModelCollection
     * @throws \Sebk\SmallOrmCore\QueryBuilder\BracketException
     * @throws \Sebk\SmallOrmCore\QueryBuilder\QueryBuilderException
     */
    public function findBy(array $conds, array $dependenciesAliases = []): ModelCollection
    {
        $query = $this->createQueryBuilder(lcfirst($this->modelClass));

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
     * Find a unique model from conditions
     * @param array $conds
     * @param array $dependenciesAliases
     * @return Model
     * @throws DaoEmptyException
     * @throws DaoException
     * @throws \Sebk\SmallOrmCore\QueryBuilder\BracketException
     * @throws \Sebk\SmallOrmCore\QueryBuilder\QueryBuilderException
     */
    public function findOneBy(array $conds, array $dependenciesAliases = []): Model {
        $results = $this->findBy($conds, $dependenciesAliases);

        if (count($results) == 0) {
            throw new DaoEmptyException("Find one with no result");
        }

        if (count($results) > 1) {
            throw new DaoException("Find one with multiple result");
        }

        return $results[0];
    }

    /**
     * Get validator class
     * @return string
     */
    public function getValidatorClass(): string | null
    {
        return $this->validatorClass;
    }
    
    /**
     * Set validator class
     * @param string $className
     * @return $this
     */
    public function setValidatorClass(string $className): AbstractDao
    {
        $this->validatorClass = $className;
        
        return $this;
    }

}