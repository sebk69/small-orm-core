<?php

/**
 * This file is a part of SebkSmallOrmCore
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Layers;

use Sebk\SmallOrmCore\Factory\Connections;

class Layers
{
    protected $connections;
    protected $config;
    protected $container;
    protected $layers;

    /**
     * Layers constructor.
     * @param Connections $connections
     * @param $config
     */
    public function __construct(Connections $connections, $config, $container)
    {
        $this->connections = $connections;
        $this->config = $config;
        $this->container = $container;
    }

    /**
     * List configured bundles
     * @return array
     */
    public function listBundles()
    {
        $result = [];
        foreach($this->config as $bundle => $bundleConfig) {
            $result[] = $bundle;
        }

        return $result;
    }

    /**
     * Load layers for a bundle
     * @param $bundle
     */
    protected function loadBundleLayers($bundle)
    {
        $bundlePath = $this->container->get('kernel')->locateResource("@".$bundle);
        $layersRootPath = $bundlePath."Resources/databaseLayers";
        if(is_dir($layersRootPath)) {
            $layersNames = scandir($layersRootPath);
            $this->layers[$bundle] = [];
            foreach($layersNames as $layerName) {
                if(substr($layerName, 0, 1) != ".") {
                    $this->layers[$bundle][] = new Layer($layersRootPath, $layerName, $this->container->get("sebk_small_orm_connections"), $this->container);
                }
            }
        }
    }

    public function execute()
    {
        // initialize executed layers
        $executed = $this->initializeExecutedLayers();

        // Load layers
        $bundles = $this->listBundles();
        foreach ($bundles as $bundle) {
            // load all layers
            $this->loadBundleLayers($bundle);

            // Unset already executed layers
            if(isset($this->layers[$bundle]) && is_array($this->layers[$bundle])) {
                foreach ($this->layers[$bundle] as $key => $layer) {
                    if (isset($executed[$bundle][$layer->getName()])) {
                        unset($this->layers[$bundle][$key]);
                    }
                }
            }
        }



        // execute layers
        do {
            $i = 0;
            foreach ($this->layers as $bundle => $bundleLayers) {
                foreach ($bundleLayers as $layerKey => $layer) {
                    // Check dependencies ar satisfied
                    $depSatisfied = true;
                    foreach ($layer->getDependencies() as $dependency) {
                        if (!isset($dependency["bundle"])) {
                            if (!isset($executed[$bundle][$dependency["layer"]])) {
                                $depSatisfied = false;
                                break;
                            }
                        } else {
                            if (!isset($executed[$dependency["bundle"]][$dependency["layer"]])) {
                                $depSatisfied = false;
                                break;
                            }
                        }
                    }

                    // execute layer if dependencies are satisfied and required parameters
                    if ($depSatisfied && $layer->getRequiredParametersSatisfied()) {
                        echo "Execute layer ".$layer->getName()."...\n";
                        if ($layer->executeScripts()) {
                            $layer->getConnection()->execute("INSERT INTO `_small_orm_layers` (`bundle`, `layer`) VALUES(:bundle, :layer);",
                                ["bundle" => $bundle, "layer" => $layer->getName()]);
                            $executed[$bundle][$layer->getName()] = true;
                            unset($this->layers[$bundle][$layerKey]);
                            $i++;
                            echo " done\n";
                        } else {
                            echo " failed\n";
                        }
                    }
                }
            }
        } while($i > 0);

        // report non executed layers
        $report = false;
        foreach ($this->layers as $bundle => $bundleLayers) {
            foreach ($bundleLayers as $layer) {
                if($layer->getRequiredParametersSatisfied()) {
                    if (!$report) {
                        $report = true;
                        echo "LAYER EXECUTE REPORT\n";
                        echo "====================\n\n";
                        echo "Some layers have not be executed because unresolved dependencies :\n\n";
                    }

                    echo "-> Layer '" . $layer->getName() . "' of bundle '" . $bundle . "'\n";
                }
            }
        }
        echo "\n";
    }

    /**
     * Initialize connections
     */
    protected function initializeExecutedLayers()
    {
        // initialize executed layers
        $executedLayers = [];

        // Get factory
        $connectionsFactory = $this->container->get("sebk_small_orm_connections");

        // Foreach connections
        foreach ($connectionsFactory->getNamesAsArray() as $connectionName) {
            // Create layers tables if not exists
            $connection = $connectionsFactory->get($connectionName);
            $connection->execute("CREATE TABLE IF NOT EXISTS `_small_orm_layers` ( `id` INT NOT NULL AUTO_INCREMENT , `bundle` VARCHAR(255) NOT NULL , `layer` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`)) ENGINE = InnoDB;");

            // List already executed layers
            $result = $connection->execute("SELECT `bundle`, `layer` from `_small_orm_layers`");
            foreach ($result as $record) {
                // And store them
                if(!isset($executedLayers[$record["bundle"]])) {
                    $executedLayers[$record["bundle"]] = [];
                }
                $executedLayers[$record["bundle"]][$record["layer"]] = true;
            }
        }

        return $executedLayers;
    }
}