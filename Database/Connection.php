<?php
/**
 * This file is a part of SebkSmallOrmCore
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Database;

use Sebk\SmallOrmCore\ORM\None;

/**
 * Connection to database
 * TODO: need to be generalized when ORM finished
 */
class Connection
{
    protected $pdo;
    protected $database;
    protected $transactionInUse = false;
    protected $dbType;
    protected $host;
    protected $user;
    protected $password;
    protected $encoding;

    /**
     * Construct and open connection
     * @param string $dbType
     * @param string $host
     * @param string $database
     * @param string $user
     * @param string $password
     * @throws ConnectionException
     */
    public function __construct($dbType, $host, $database, $user, $password, $encoding)
    {
        $this->database = $database;
        $this->dbType = $dbType;
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->encoding = $encoding;

        $this->connect();
    }

    /**
     * Connect to database, use existing connection if exists
     *
     * NB: "None DB" existing in case of a unit tests without any connection to a database.
     *
     * @throws ConnectionException
     */
    protected function connect()
    {
        switch ($this->dbType) {
            case "mysql":
                // Connect to database
                $connectionString = "mysql:dbname=$this->database;host=$this->host;charset=$this->encoding";
                try {
                    $this->pdo = new \PDO($connectionString, $this->user, $this->password);
                } catch (\PDOException $e) {
                    // Create database if not exists
                    $connectionString = "mysql:host=$this->host;charset=$this->encoding";
                    try {
                        $this->pdo = new \PDO($connectionString, $this->user, $this->password);
                    } catch (\PDOException $e) {
                        throw new ConnectionException($e->getMessage());
                    }
                    $statement = $this->pdo->prepare("create database `$this->database`;use `$this->database`;");
                    if(!$statement->execute()) {
                        $errInfo = $statement->errorInfo();
                        throw new ConnectionException("Fail to execute request : SQLSTATE[".$errInfo[0]."][".$errInfo[1]."] ".$errInfo[2]);
                    }
                }
                break;

            // For unit testing purpose
            case 'none':
                $this->pdo = new None();
                break;

            default:
                throw new ConnectionException("Database type is not developed for now");
        }
    }

    /**
     * Get database
     * @return string
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Execute sql instruction
     * @param $sql
     * @param array $params
     * @param bool $retry
     * @return mixed
     * @throws ConnectionException
     */
    public function execute($sql, $params = array(), $retry = false)
    {
        if ($this->dbType == "mysql" && $this->pdo->getAttribute(\PDO::ATTR_SERVER_INFO)=='MySQL server has gone away') {
            $this->connect();
        }

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $param => $value) {
            $statement->bindValue(":".$param, $value);
        }
        if ($statement->execute()) {
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $errInfo = $statement->errorInfo();
            if($errInfo[0] == "HY000" && $errInfo[1] == "2006" && !$retry) {
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
     * Return true if transaction in use
     * @return bool
     */
    public function getTransactionInUse()
    {
        return $this->transactionInUse;
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