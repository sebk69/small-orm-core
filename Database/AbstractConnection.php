<?php
/**
 * This file is a part of small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Database;

abstract class AbstractConnection
{
    protected $database;
    protected $transactionInUse = false;
    protected $dbType;
    protected $host;
    protected $user;
    protected $password;
    protected $encoding;
    protected $tryCreateDatabase;

    /**
     * Construct and open connection
     * @param string $dbType
     * @param string $host
     * @param string $database
     * @param string $user
     * @param string $password
     * @throws ConnectionException
     */
    public function __construct($dbType, $host, $database, $user, $password, $encoding, $createDatabase = false)
    {
        $this->database = $database;
        $this->dbType = $dbType;
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->encoding = $encoding;
        $this->tryCreateDatabase = $createDatabase;
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
     * Connect to database, use existing connection if exists
     */
    abstract public function connect($forceReconnect = false);

    /**
     * Execute sql instruction
     * @param $sql
     * @param array $params
     * @param bool $retry
     * @return mixed
     * @throws ConnectionException
     */
    abstract public function execute($sql, $params = array(), $retry = false);

    /**
     * Start transaction
     * @return $this
     * @throws ConnectionException
     * @throws TransactionException
     */
    abstract public function startTransaction();

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
    abstract public function commit();

    /**
     * Rollback transaction
     * @return $this
     * @throws ConnectionException
     * @throws TransactionException
     */
    abstract public function rollback();


    /**
     * Get last insert id
     * @return int
     */
    abstract public function lastInsertId();

}
