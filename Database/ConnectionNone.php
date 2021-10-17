<?php

/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Database;

use Sebk\SmallOrmCore\ORM\None;

/**
 * Connection to "none" database : use for unit tests
 */
class ConnectionNone extends AbstractConnection
{
    /** @var None */
    protected $pdo;

    /**
     * Connect to database, use existing connection if exists
     */
    public function connect($force = false)
    {
        $this->pdo = new None();
    }

    /**
     * Execute sql instruction
     * @param $sql
     * @param array $params
     * @param bool $retry
     * @return mixed
     */
    public function execute($sql, $params = array(), $retry = false)
    {
        $statement = $this->pdo->prepare($sql);

        foreach ($params as $param => $value) {
            $statement->bindValue(":".$param, $value);
        }

        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Start transaction
     * @return $this
     * @throws ConnectionException
     * @throws TransactionException
     */
    public function startTransaction()
    {
        $this->transactionInUse = true;

        return $this;
    }

    /**
     * Commit transaction
     * @return $this
     * @throws TransactionException
     */
    public function commit()
    {
        if(!$this->getTransactionInUse()) {
            throw new TransactionException("Transaction not started");
        }

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

        return $this;
    }

    /**
     * Get last insert id
     * @return int
     */
    public function lastInsertId()
    {
        return 0;
    }

}
