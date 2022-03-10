<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Factory;

use Sebk\SmallOrmCore\Database\AbstractConnection;
use Sebk\SmallOrmCore\Database\ConnectionException;

/**
 * Factory for connections
 * Keep opened connections into memory for performance purpose
 */
class Connections
{
    public static $namespaces = [
        '\Sebk\SmallOrmCore\Database\\',
        '\Sebk\SmallOrmSwoole\Database\\',
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
     * Get connection class for a config
     * @param array $connectionConfig
     * @return void
     */
    private function getClass($type)
    {
        // Get connection class name
        $typeExploded = explode("-", $type);
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

        if (!isset($class)) {
            throw new \Exception("Connection type ($type) not exists !");
        }

        return $class;
    }

    /**
     * Get a connection
     * @param type $connectionName
     * @return AbstractConnection
     * @throws ConfigurationException
     */
    public function get($connectionName = 'default', $new = false)
    {
        if ($connectionName == 'default' && !$new) {
            $connectionName = $this->defaultConnection;
        }

        if (!isset($this->config[$connectionName])) {
            throw new ConfigurationException("The connection '$connectionName' is not configured in app/config");
        }

        if (!isset(static::$connections[$connectionName]) || $new) {
            // Get config for connection
            $connectionConfig = $this->config[$connectionName];
            $class = $this->getClass($connectionConfig["type"]);

            // Check connection type exists
            if (!class_exists($class)) {
                throw new ConnectionException("The connection type '" . $connectionConfig['type'] . "' does not exists ($class)");
            }

            if (!$new) {
                // Create instance
                static::$connections[$connectionName] = new $class(
                    $connectionConfig['type'], $connectionConfig['host'],
                    $connectionConfig['database'], $connectionConfig['user'],
                    $connectionConfig['password'],
                    $connectionConfig['encoding'],
                    isset($connectionConfig['tryCreateDatabase']) ?? $connectionConfig['tryCreateDatabase']
                );
            } else {
                return new $class(
                    $connectionConfig['type'], $connectionConfig['host'],
                    $connectionConfig['database'], $connectionConfig['user'],
                    $connectionConfig['password'],
                    $connectionConfig['encoding'],
                    isset($connectionConfig['tryCreateDatabase']) ?? $connectionConfig['tryCreateDatabase']
                );
            }
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

    /**
     * Force all connections to reconnect
     */
    public function reconnectAll()
    {
        foreach (self::$connections as $connection) {
            $connection->connect(true);
        }
    }
}
