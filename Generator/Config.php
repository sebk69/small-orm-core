<?php
/**
 * This file is a part of SebkSmallOrmCore
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Generator;


class Config
{
    protected $bundle;
    protected $connection;
    protected $container;

    /**
     * Config constructor.
     * @param $bundle
     * @param $connection
     * @param $container
     */
    public function __construct($bundle, $connection, $container)
    {
        $this->bundle = $bundle;
        $this->connection = $connection;
        $this->container = $container;
    }

    /**
     * Get config path
     * @return string
     */
    public function getConfigPath()
    {
        $dir = $this->container->get('kernel')->locateResource("@".$this->bundle)."Resources/SmallOrm";

        if(!is_dir($dir)) {
            mkdir($dir);
        }

        return $dir."/".$this->connection.".txt";
    }

    /**
     * Get tables from config
     * @return array
     */
    public function getTables()
    {
        $data = @file_get_contents($this->getConfigPath());

        if(!empty($data)) {
            return explode("\n", $data);
        }

        return [];
    }

    /**
     * Return true if table exists in config
     * @param $table
     * @return bool
     */
    public function tableExists($table)
    {
        $configTables = $this->getTables();

        foreach($configTables as $configTable) {
            if($table == $configTable) {
                return true;
            }
        }

        return false;
    }

    /**
     * Write tables to config
     * @param $tables
     * @return $this
     */
    public function writeTables($tables)
    {
        $data = implode("\n", $tables);

        file_put_contents($this->getConfigPath(), $data);

        return $this;
    }

    /**
     * Add a table to config
     * @param $table
     * @return $this
     */
    public function addTable($table)
    {
        if(!$this->tableExists($table)) {
            $tables = $this->getTables();
            $tables[] = $table;
            $this->writeTables($tables);
        }

        return $this;
    }
}