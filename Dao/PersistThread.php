<?php

namespace Sebk\SmallOrmCore\Dao;

use Sebk\SmallOrmCore\Database\AbstractConnection;

class PersistThread
{
    const PERSIST_TYPE = "PERSIST_TYPE";
    const DELETE_TYPE = "DELETE_TYPE";
    const START_TRANSACTION_TYPE = "START_TRANSACTION_TYPE";
    const COMMIT_TYPE = "COMMIT_TYPE";
    const ROLLBACK_TYPE = "ROLLBACK_TYPE";

    /**
     * @var bool
     */
    protected $transactionStarted = false;

    /**
     * @var Model[]
     */
    protected $bag = [];

    /**
     * @var AbstractConnection
     */
    protected $connection;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @var bool
     */
    protected $flushOnInsert = true;

    /**
     * @var Model
     */
    protected $lastInsertedModel = null;
    
    /**
     * @param AbstractConnection $connection
     */
    public function __construct(AbstractConnection $connection)
    {
        $this->connection = $connection;
        $this->connection->connect();
    }

    /**
     * Set flush on insert
     * @param $value
     * @return $this
     */
    public function setFlushOnInsert($value = true)
    {
        $this->flushOnInsert = $value;
        
        return $this;
    }

    /**
     * Persist a model in the thread
     * @param Model $model
     * @return $this
     */
    public function pushPersist(Model $model)
    {
        if ($this->flushOnInsert && $model->fromDb == false) {
            $this->flush();
        }

        $this->bag[] = [
            "model" => $model,
            "type" => self::PERSIST_TYPE,
        ];

        if ($this->flushOnInsert && $model->fromDb == false) {
            $this->flush();
        }

        return $this;
    }

    /**
     * Delete a model in the thread
     * @param Model $model
     * @return $this
     */
    public function pushDelete(Model $model)
    {
        $this->bag[] = [
            "model" => $model,
            "type" => self::DELETE_TYPE,
        ];

        return $this;
    }

    /**
     * Start transaction in the thread
     * @return $this
     */
    public function startTransaction()
    {
        $this->bag[] = [
            "model" => null,
            "type" => self::START_TRANSACTION_TYPE,
        ];

        return $this;
    }

    /**
     * Commit thread
     * @return $this
     */
    public function commit()
    {
        $this->bag[] = [
            "model" => null,
            "type" => self::COMMIT_TYPE,
        ];
        $this->flush();

        return $this;
    }

    /**
     * Roolback thread
     * @return $this
     */
    public function rollback()
    {
        $this->bag = [];
        if ($this->transactionStarted) {
            $this->bag[] = [
                "model" => null,
                "type" => self::ROLLBACK_TYPE,
            ];
            $this->flush();
        }
        
        return $this;
    }

    /**
     * Get sql
     * @param Model|null $model
     * @param $type
     * @param $key
     * @return array
     * @throws DaoException
     */
    protected function getSqlForModel(?Model $model, $type, $key)
    {
        $params = [];

        switch ($type) {
            case $type == self::PERSIST_TYPE:
            if (method_exists($model, "beforeSave")) {
                $model->beforeSave();
            }

            if ($model->fromDb) {
                list($sql, $params) = $model->getDao()->getUpdateSql($model, $key . "_");
            } else {
                list($sql, $params) = $model->getDao()->getInsertSql($model, $key . "_");
                $this->lastInsertedModel = $model;
            }

            if (method_exists($model, "afterSave")) {
                $model->afterSave();
            }

            $model->fromDb = true;
            $model->altered = false;
            break;

            case self::DELETE_TYPE:
                if (method_exists($model, "beforeDelete")) {
                    $model->beforeDelete();
                }

                list($sql, $params) = $model->getDao()->getDeleteSql($model, $key . "_");

                if (method_exists($model, "afterDelete")) {
                    $model->afterDelete();
                }
                break;
                
            case self::START_TRANSACTION_TYPE:
                $this->transactionStarted = true;
                $sql = "START TRANSACTION;";
                break;
            case self::COMMIT_TYPE:
                $this->transactionStarted = false;
                $sql = "COMMIT;";
                break;
            case self::ROLLBACK_TYPE:
                $this->transactionStarted = false;
                $sql = "COMMIT;";
                break;
        }

        return [$sql, $params];
    }

    /**
     * Flush thread
     * @return $this
     * @throws DaoException
     */
    public function flush()
    {
        $sql = "";
        $params = [];

        foreach ($this->bag as $key => $element) {
            list($atomicSql, $atomicparams) = $this->getSqlForModel($element["model"], $element["type"], $key);
            $sql .= $atomicSql;
            $params = array_merge($params, $atomicparams);
        }

        if (empty($sql)) {
            return $this;
        }

        if (method_exists($this->connection, "getPdo")) {
            if ($this->pdo == null) {
                $this->pdo = $this->connection->getPdo();
            }

            $statement = $this->pdo->prepare($sql);
            foreach ($params as $param => $value) {
                $statement->bindValue(":" . $param, $value);
            }

            $statement->execute();
        } else {
            $this->connection->execute($sql, $params);
        }

        if ($this->flushOnInsert && $this->lastInsertedModel != null) {
            if (method_exists($this->connection, "getPdo")) {
                $id = $this->pdo->lastInsertId();
            } else {
                $id = $this->connection->lastInsertId();
            }
            foreach ($this->lastInsertedModel->getPrimaryKeys() as $key => $value) {
                if ($value === null) {
                    $method = "raw" . $key;
                    $this->lastInsertedModel->$method($id);
                }
            }
            $this->lastInsertedModel = null;
        }
        
        $this->bag = [];

        return $this;
    }

    /**
     * Close thread
     * @return $this
     */
    public function close()
    {
        if (method_exists($this->connection, "getPdo") && $this->pdo != null) {
            $this->connection->pool->put($this->pdo);
            $this->pdo = null;
        }

        return $this;
    }
}