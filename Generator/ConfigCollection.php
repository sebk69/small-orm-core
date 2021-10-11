<?php
/**
 * This file is a part of SebkSmallOrmCore
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Generator;


class ConfigCollection
{
    protected $connectionName;
    protected $container;
    protected $generatorConfigs;

    /**
     * ConfigCollection constructor.
     * @param $connectionName
     * @param $container
     */
    public function __construct($connectionName, $container)
    {
        $this->connectionName = $connectionName;
        $this->container = $container;
    }

    /**
     * Load configs for all bundles
     */
    public function loadConfigs($withoutVendor = true)
    {
        $config = $this->container->getParameter("sebk_small_orm.bundles");

        foreach($config as $bundle => $connection) {
            $this->generatorConfigs[$bundle] = new Config($bundle, $this->connectionName, $this->container);
        }
    }

    /**
     * Get bundle where table is configured
     * @param $dbTableName
     * @return string
     * @throws TableNotFoundException
     */
    public function getTableBundle($dbTableName)
    {
        foreach ($this->generatorConfigs as $bundle => $generatorConfig) {
            if ($generatorConfig->tableExists($dbTableName)) {
                return $bundle;
            }
        }

        throw new TableNotFoundException("The table ".$dbTableName." is not in any bundle");
    }

    /**
     * Get all configured tables with bundle associated
     * @return array
     */
    public function getAllConfiguredTables()
    {
        $result = [];
        foreach ($this->generatorConfigs as $bundle => $generatorConfig) {
            foreach ($generatorConfig->getTables() as $dbTableName) {
                $result[] = ["bundle" => $bundle, "table" => $dbTableName];
            }
        }

        return $result;
    }
}