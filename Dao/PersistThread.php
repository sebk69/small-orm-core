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
    protected $withTransaction;

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

    public function __construct(AbstractConnection $connection)
    {
        $this->connection = $connection;
        $this->connection->connect();
    }

    public function pushPersist(Model $model)
    {
        $this->bag[] = [
            "model" => $model,
            "type" => self::PERSIST_TYPE,
        ];

        return $this;
    }
    
    public function pushDelete(Model $model)
    {
        $this->bag[] = [
            "model" => $model,
            "type" => self::DELETE_TYPE,
        ];

        return $this;
    }
    
    public function startTransaction()
    {
        $this->bag[] = [
            "model" => null,
            "type" => self::START_TRANSACTION_TYPE,
        ];

        return $this;
    }
    
    public function commit()
    {
        $this->bag[] = [
            "model" => null,
            "type" => self::COMMIT_TYPE,
        ];
        $this->flush();

        return $this;
    }
    
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
            case self::ROLLBACK_TYPE_TYPE:
                $this->transactionStarted = false;
                $sql = "COMMIT;";
                break;
        }

        return [$sql, $params];
    }

    public function flush()
    {
        $sql = "";
        $params = [];

        foreach ($this->bag as $key => $element) {
            list($atomicSql, $atomicparams) = $this->getSqlForModel($element["model"], $element["type"], $key);
            $sql .= $atomicSql;
            $params = array_merge($params, $atomicparams);
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
            $this->pool->execute($sql, $params);
        }
    }

    public function close()
    {
        if (method_exists($this->connection, "getPdo") && $this->pdo != null) {
            $this->connection->pool->put($this->pdo);
            $this->pdo = null;
        }
    }
}