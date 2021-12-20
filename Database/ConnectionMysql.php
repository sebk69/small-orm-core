<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Database;

/**
 * Connection to mysql database
 */
class ConnectionMysql extends AbstractConnection
{
    protected $pdo;

    /**
     * Connect to database, use existing connection if exists
     * @throws ConnectionException
     */
    public function connect($forceReconnect = false)
    {
        // Connect to database
        $connectionString = "mysql:dbname=$this->database;host=$this->host;charset=$this->encoding";
        if ($this->pdo == null || $forceReconnect) {
            try {
                $this->pdo = new \PDO($connectionString, $this->user, $this->password);
            } catch (\PDOException $e) {
                throw new ConnectionException($e->getMessage());
            }

            // Create database if not exists
            $statement = $this->pdo->prepare("create database if not exists `$this->database`;use `$this->database`;");
            if(!$statement->execute()) {
                $errInfo = $statement->errorInfo();
                throw new ConnectionException("Fail to execute request : SQLSTATE[".$errInfo[0]."][".$errInfo[1]."] ".$errInfo[2]);
            }
        }

        return $this->pdo;
    }

    /**
     * Execute sql instruction
     * @param $sql
     * @param array $params
     * @param bool $retry
     * @return mixed
     * @throws ConnectionException
     */
    public function execute($sql, $params = array(), $retry = false, $forceConnection = null)
    {
        if ($forceConnection === null) {
            $pdo = $this->connect();
        } else {
            $pdo = $forceConnection;
        }

        if ($pdo->getAttribute(\PDO::ATTR_SERVER_INFO)=='MySQL server has gone away') {
            $pdo = null;
            $this->connect();
        }

        $statement = $pdo->prepare($sql);

        foreach ($params as $param => $value) {
            $statement->bindValue(":".$param, $value);
        }
        if ($statement->execute()) {
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $errInfo = $statement->errorInfo();
            if($errInfo[0] == "HY000" && $errInfo[1] == "2006" && !$retry) {
                $pdo = null;
                $this->connect();
                return $this->execute($sql, $params, true);
            } else {
                throw new ConnectionException("Fail to execute request : SQLSTATE[" . $errInfo[0] . "][" . $errInfo[1] . "] " . $errInfo[2]);
            }
        }
    }

    /**
     * Start transaction
     * @return $this
     * @throws ConnectionException
     * @throws TransactionException
     */
    public function startTransaction()
    {
        if($this->getTransactionInUse()) {
            throw new TransactionException("Transaction already started");
        }

        $this->execute("START TRANSACTION");
        $this->transactionInUse = true;

        return $this;
    }

    /**
     * Commit transaction
     * @return $this
     * @throws ConnectionException
     * @throws TransactionException
     */
    public function commit()
    {
        if(!$this->getTransactionInUse()) {
            throw new TransactionException("Transaction not started");
        }

        $this->execute("COMMIT");

        $this->transactionInUse = false;

        return $this;
    }

    /**
     * Rollback transaction
     * @return $this
     * @throws ConnectionException
     * @throws TransactionException
     */
    public function rollback()
    {
        if(!$this->getTransactionInUse()) {
            throw new TransactionException("Transaction not started");
        }

        $this->execute("ROLLBACK");

        $this->transactionInUse = false;

        return $this;
    }

    /**
     * Get last insert id
     * @return int
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
}
