<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Factory;

use Psr\Container\ContainerInterface;
use Sebk\SmallOrmCore\Dao\AbstractDao;

/**
 *
 */
class Dao
{
    protected $connectionFactory;
    protected $container;
    protected static $loadedDao = array();
    protected $mocked = [];

    /**
     * Construct dao factory
     * @param Connections $connectionFactory
     * @param $container
     */
    public function __construct(Connections $connectionFactory, ContainerInterface $container)
    {
        $this->connectionFactory = $connectionFactory;
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
     * @param string $dao
     * @param string $class
     * @return $this
     */
    public function mock(string $dao, string $class): Dao
    {
        $this->mocked[$dao] = $class;

        return $this;
    }

    /**
     * Create new dao object
     * @param string $dao
     * @param $useConnection
     * @return AbstractDao
     * @throws ConfigurationException
     * @throws DaoNotFoundException
     * @throws \ReflectionException
     */
    public function getNew(string $dao, $useConnection = null): AbstractDao {
        return $this->get($dao, $useConnection, true);
    }
    
    /**
     * Get dao of a model
     * @param string $dao
     * @return AbstractDao
     * @throws ConfigurationException
     * @throws DaoNotFoundException
     * @throws \ReflectionException
     */
    public function get(string $dao, $new = false): AbstractDao
    {
        // Dao mocked ?
        if(isset($this->mocked[$dao])) {
            // return new instance of mock
            $class = $this->mocked[$dao];
            return new $class(
                $this->container->get("sebk_small_orm_connections"),
                $this,
                $this->container
            );
        }

        // Check existance of dao
        if (class_exists($dao)) {
            if (!(new \ReflectionClass($dao))->isAbstract()) {
                if (!$new) {
                    // Instantiate and return dao
                    static::$loadedDao[$dao] = new $dao($this->connectionFactory,
                        $this,
                        $this->container);

                    return static::$loadedDao[$dao];
                } else {
                    return new $dao($this->connectionFactory,
                        $this,
                        $this->container);
                }
            } else {
                throw new DaoNotFoundException("$dao is abstract");
            }
        }

        throw new DaoNotFoundException("class $dao does not exists");
    }

    /**
     * Get file where is defined the dao
     * @param string $dao
     * @param bool $evenIfNotFound
     * @return string
     * @throws ConfigurationException
     * @throws DaoNotFoundException
     * @throws \ReflectionException
     */
    public function getFile($dao, $evenIfNotFound = false)
    {
        // return file
        return $this->getFileForClass($dao, $evenIfNotFound);
    }

    /**
     * Get file where is defined the model
     * @param $dao
     * @param $evenIfNotFound
     * @return string
     * @throws DaoNotFoundException
     * @throws \ReflectionException
     */
    public function getModelFile($dao, $evenIfNotFound = false)
    {
        // TODO evenIfNotFound
        return $this->getFileForClass($dao::modelClass);
    }

    /**
     * Get the file for a class
     * @param $class
     * @param $evenIfNotFound
     * @return false|string|void
     * @throws DaoNotFoundException
     */
    private function getFileForClass($class, $evenIfNotFound = false)
    {
        // Use reflector if class exists
        if (class_exists($class)) {
            $reflector = new \ReflectionClass($class);

            // return file name
            return $reflector->getFileName();
        } elseif(!$evenIfNotFound) {
            throw new DaoNotFoundException("Class not found : $fullClassName");
        }
        
        // TODO evenIfNotFound
    }
}