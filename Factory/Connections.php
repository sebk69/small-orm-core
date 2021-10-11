<?php
/**
 * This file is a part of SebkSmallOrmCore
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Factory;

use Sebk\SmallOrmCore\Database\Connection;

/**
 * Factory for connections
 * Keep opened connections into memory for performance purpose
 */
class Connections
{
    public $config;
    public $defaultConnection;
    public static $connections = array();

    /**
     * Construct factory
     * @param array $config
     * @param string $defaultConnection
     */
    public function __construct($config, $defaultConnection)
    {
        $this->config            = $config;
        $this->defaultConnection = $defaultConnection;
    }

    /**
     * Get a connection
     * @param type $connectionName
     * @return Connection
     * @throws ConfigurationException
     */
    public function get($connectionName = 'default')
    {
        if ($connectionName == 'default') {
            $connectionName = $this->defaultConnection;
        }

        if (!isset($this->config[$connectionName])) {
            throw new ConfigurationException("The connection '$connectionName' is not configured in app/config");
        }

        if (!isset(static::$connections[$connectionName])) {
            $connectionConfig                     = $this->config[$connectionName];
            static::$connections[$connectionName] = new Connection(
                $connectionConfig['type'], $connectionConfig['host'],
                $connectionConfig['database'], $connectionConfig['user'],
                $connectionConfig['password'],
                $connectionConfig['encoding']
            );
        }

        return static::$connections[$connectionName];
    }

    /**
     * Get list of connections names
     * @return array
     */
    public function getNamesAsArray()
    {
        $array = array();
        foreach ($this->config as $connectionName => $connectionParams) {
            $array[] = $connectionName;
        }

        return $array;
    }
}