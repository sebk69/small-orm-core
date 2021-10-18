<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Factory;

use Sebk\SmallOrmCore\Dao\AbstractDao;

/**
 *
 */
class Dao
{
    protected $connectionFactory;
    protected $config;
    protected $container;
    protected static $loadedDao = array();
    protected $mocked = [];

    /**
     * Construct dao factory
     * @param \Sebk\SmallOrmCore\Factory\Connections $connectionFactory
     * @param type $config
     */
    public function __construct(Connections $connectionFactory, $config, $container)
    {
        $this->connectionFactory = $connectionFactory;
        $this->config            = $config;
        $this->container         = $container;
    }

    /**
     * Reset Factory elements
     *
     * @return $this
     */
    public function reset()
    {
        static::$loadedDao = [];

        return $this;
    }

    /**
     * Mock a dao
     * @param string $bundle
     * @param string $dao
     * @param string $class
     * @return Dao
     */
    public function mock(string $bundle, string $dao, string $class): Dao
    {
        $this->mocked[$bundle."*".$dao] = $class;

        return $this;
    }

    /**
     * Get dao of a model
     * @param string $bundle
     * @param string $model
     * @return AbstractDao
     * @throws ConfigurationException
     * @throws DaoNotFoundException
     * @throws \ReflectionException
     */
    public function get(string $bundle, string $model, $useConnection = null): AbstractDao
    {
        if (!isset($this->config[$bundle])) {
            throw new ConfigurationException("Bundle '$bundle' is not configured");
        }

        // Dao mocked ?
        if(isset($this->mocked[$bundle."*".$model])) {
            // return new instance of mock
            $class = $this->mocked[$bundle."*".$model];
            return new $class(
                $this->container->get("sebk_small_orm_connections")->get(),
                $this->container->get("sebk_small_orm_dao"),
                $this->container->get("sebk_small_orm_dao")->getModelNamespace("default", $bundle),
                $model,
                $bundle,
                $this->container
            );
        }

        foreach ($this->config[$bundle]["connections"] as $connectionName => $connectionsParams) {
            if ($connectionName == $useConnection || $useConnection === null) {
                // If dao already instancited => return dao instance
                if (isset(static::$loadedDao[$bundle][$model][$connectionName])) {
                    return static::$loadedDao[$bundle][$model][$connectionName];
                }

                // Check if connection is an alias of other connection
                if (is_string($connectionsParams) && substr($connectionsParams, 0, 1) == "@") {
                    // Get connection name
                    $aliasConnnection = str_replace("@", "", $connectionsParams);

                    // Target connection exists for this bundle
                    if (!isset($this->config[$bundle]["connections"][$aliasConnnection])) {
                        throw new DaoNotFoundException("L'alias de connection est inconnu ($aliasConnnection)");
                    }

                    // We get namespaces from target connection
                    $daoNamespace = $this->config[$bundle]["connections"][$aliasConnnection]["dao_namespace"];
                    $modelNamespace = $this->config[$bundle]["connections"][$aliasConnnection]["model_namespace"];
                } else {
                    // Otherwise, get namespace from connection
                    $daoNamespace = $connectionsParams["dao_namespace"];
                    $modelNamespace = $connectionsParams["model_namespace"];
                }

                // Check existance of dao
                $className = $daoNamespace . '\\' . $model;
                if (class_exists($className)) {
                    if (!(new \ReflectionClass($className))->isAbstract()) {
                        // Instantiate and return dao
                        static::$loadedDao[$bundle][$model][$connectionName] = new $className($this->connectionFactory->get($connectionName),
                            $this, $modelNamespace, $model,
                            $bundle,
                            $this->container);

                        return static::$loadedDao[$bundle][$model][$connectionName];
                    } else {
                        throw new DaoNotFoundException("Dao of model $model of bundle $bundle is abstract");
                    }
                }
            }
        }

        throw new DaoNotFoundException("Dao of model $model of bundle $bundle not found");
    }

    /**
     * Get dao directory for a bundle and a connection
     * @param $bundle
     * @param $connection
     * @return string
     * @throws ConfigurationException
     */
    public function getDaoDir($bundle, $connection)
    {
        if (!isset($this->config[$bundle])) {
            throw new ConfigurationException("Bundle '$bundle' is not configured");
        }

        if(!isset($this->config[$bundle]["connections"][$connection])) {
            throw new ConfigurationException(("Connection '$connection' is not configured for bundle '$bundle'"));
        }

        $parts = explode("\\", $this->config[$bundle]["connections"][$connection]["dao_namespace"]);
        $relativePath = $parts[count($parts) - 1];

        return $this->container->get('kernel')->locateResource("@".$bundle)."/".$relativePath;
    }

    /**
     * Return the class name with namespace
     * @param $connectionNameOfDao
     * @param $bundle
     * @param $model
     * @return string
     * @throws ConfigurationException
     */
    public function getDaoFullClassName($connectionNameOfDao, $bundle, $model)
    {
        // get namespace
        $namespace = $this->getDaoNamespace($connectionNameOfDao, $bundle);

        // get full class name
        $className = $namespace . '\\' . $model;

        return $className;
    }

    /**
     * Get namespace for a connection and bundle
     * @param $connectionNameOfDao
     * @param $bundle
     * @return mixed
     * @throws ConfigurationException
     */
    public function getDaoNamespace($connectionNameOfDao, $bundle)
    {
        // bundle exists in configuration
        if (!isset($this->config[$bundle])) {
            throw new ConfigurationException("Bundle '$bundle' is not configured");
        }

        // check connection exists in bundle
        if (!isset($this->config[$bundle]["connections"][$connectionNameOfDao])) {
            throw new ConfigurationException("Connection '".$connectionNameOfDao."' is not found for bundle '".$bundle."'");
        }

        return $this->config[$bundle]["connections"][$connectionNameOfDao]["dao_namespace"];
    }

    /**
     * Return the class name of model with namespace
     * @param $connectionNameOfDao
     * @param $bundle
     * @param $model
     * @return string
     * @throws ConfigurationException
     */
    public function getModelFullClassName($connectionNameOfDao, $bundle, $model)
    {
        // get namespace
        $namespace = $this->getModelNamespace($connectionNameOfDao, $bundle);

        // get full class name
        $className = $namespace . '\\' . $model;

        return $className;
    }

    /**
     * Get namespace of model for a connection and bundle
     * @param $connectionNameOfDao
     * @param $bundle
     * @return mixed
     * @throws ConfigurationException
     */
    public function getModelNamespace($connectionNameOfDao, $bundle)
    {
        // bundle exists in configuration
        if(!isset($this->config[$bundle])) {
            throw new ConfigurationException("Bundle '$bundle' is not configured");
        }

        // check connection exists in bundle
        if (!isset($this->config[$bundle]["connections"][$connectionNameOfDao])) {
            throw new ConfigurationException("Connection '".$connectionNameOfDao."' is not found for bundle '".$bundle."'");
        }

        return $this->config[$bundle]["connections"][$connectionNameOfDao]["model_namespace"];
    }

    /**
     * Get file where is defined the dao
     * @param $connectionNameOfDao
     * @param $bundle
     * @param $model
     * @param bool $evenIfNotFound
     * @return string
     * @throws ConfigurationException
     * @throws DaoNotFoundException
     * @throws \ReflectionException
     */
    public function getFile($connectionNameOfDao, $bundle, $model, $evenIfNotFound = false)
    {
        // get class name
        $className = $this->getDaoFullClassName($connectionNameOfDao, $bundle, $model);

        // return file
        return $this->getFileForClass($bundle, $className, $evenIfNotFound);
    }

    /**
     * Get file where is defined the model
     * @param $connectionNameOfDao
     * @param $bundle
     * @param $model
     * @param bool $evenIfNotFound
     * @return string
     * @throws ConfigurationException
     * @throws DaoNotFoundException
     * @throws \ReflectionException
     */
    public function getModelFile($connectionNameOfDao, $bundle, $model, $evenIfNotFound = false)
    {
        // get class name
        $className = $this->getModelFullClassName($connectionNameOfDao, $bundle, $model);

        // return file
        return $this->getFileForClass($bundle, $className, $evenIfNotFound);
    }

    /**
     * Get the file for a class
     * @param $bundle
     * @param $fullClassName
     * @param bool $evenIfNotFound
     * @return string
     * @throws DaoNotFoundException
     * @throws \ReflectionException
     */
    private function getFileForClass($bundle, $fullClassName, $evenIfNotFound = false)
    {
        // Use reflector if class exists
        if (class_exists($fullClassName)) {
            $reflector = new \ReflectionClass($fullClassName);

            // return file name
            return $reflector->getFileName();
        } elseif(!$evenIfNotFound) {
            throw new DaoNotFoundException("Class not found : $fullClassName");
        }

        // get namespace parts
        $nameSpaceParts = explode("\\", $fullClassName);

        // create relative path to file
        if (!property_exists($this->container->get("kernel"), "swoft")) {
            if ($nameSpaceParts[0] != "App") {
                unset($nameSpaceParts[0]);
                unset($nameSpaceParts[1]);
            } else {
                unset($nameSpaceParts[0]);
                unset($nameSpaceParts[1]);
                //unset($nameSpaceParts[2]);
            }
        } else {
            foreach ($nameSpaceParts as $i => $part) {
                unset($nameSpaceParts[$i]);
                if ($part == $bundle) {
                    break;
                }
            }
        }
        $relativePath = "";
        foreach($nameSpaceParts as $nameSpacePart) {
            $relativePath .= $nameSpacePart."/";
        }
        $relativePath = substr($relativePath, 0, strlen($relativePath) - 1).".php";

        // create file path
        return $this->container->get('kernel')->locateResource("@".$bundle)."/".$relativePath;
    }
}
