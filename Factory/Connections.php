<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Factory;

use Sebk\SmallOrmCore\Database\ConnectionException;
use Sebk\SmallOrmCore\Database\ConnectionMysql;

/**
 * Factory for connections
 * Keep opened connections into memory for performance purpose
 */
class Connections
{
    public static $namespaces = [
        '\Sebk\SmallOrmCore\Database\\',
    ];

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
     * @return ConnectionMysql
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
            // Get config for connection
            $connectionConfig = $this->config[$connectionName];

            // Get connection class name
            $typeExploded = explode("-", $connectionConfig['type']);
            $decomposedClass = [];
            foreach ($typeExploded as $item) {
                $decomposedClass[] = ucfirst($item);
            }

            foreach (static::$namespaces as $namespace) {
                $class = $namespace . 'Connection' . implode("", $decomposedClass);
                if (class_exists($class)) {
                    break;
                }
            }

            // Check connection type exists
            if (!class_exists($class)) {
                throw new ConnectionException("The connection type '" . $connectionConfig['type'] . "' does not exists ($class)");
            }

            // Create instance
            static::$connections[$connectionName] = new $class(
                $connectionConfig['type'], $connectionConfig['host'],
                $connectionConfig['database'], $connectionConfig['user'],
                $connectionConfig['password'],
                $connectionConfig['encoding'],
                isset($connectionConfig['tryCreateDatabase']) ?? $connectionConfig['tryCreateDatabase']
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
