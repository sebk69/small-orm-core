<?php

/**
 * This file is a part of sebk/small-orm-core
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
    protected $layers = [];

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
     * List configured folders
     * @return array
     */
    public function listFolders()
    {
        $result = [];
        foreach($this->config['selectors'] as $selector => $selectorConfig) {
            if (array_key_exists('layers_path', $selectorConfig)) {
                $result[$selector] = $selectorConfig['layers_path'];
            }
        }

        return $result;
    }

    /**
     * Load layers for a selector
     * @param $selector
     * @param $folder
     */
    protected function loadSelectorLayers($selector, $folder)
    {
        $layersRootPath = $folder;
        if (is_dir($layersRootPath)) {
            $layersNames = scandir($layersRootPath);
            $this->layers[$selector] = [];
            foreach ($layersNames as $layerName) {
                if (substr($layerName, 0, 1) != ".") {
                    $this->layers[$selector][] = new Layer($layersRootPath, $layerName, $this->container->get("sebk_small_orm_connections"), $this->container);
                }
            }
        }
    }

    public function execute()
    {
        // initialize executed layers
        $executed = $this->initializeExecutedLayers();

        // Load layers
        $selectors = $this->listFolders();
        foreach ($selectors as $selector => $folder) {
            // load all layers
            $this->loadSelectorLayers($selector, $folder);

            // Unset already executed layers
            if(isset($this->layers[$selector]) && is_array($this->layers[$selector])) {
                foreach ($this->layers[$selector] as $key => $layer) {
                    if (array_key_exists($selector, $executed)) {
                        unset($this->layers[$selector][$key]);
                    }
                }
            }
        }

        // execute layers
        do {
            $i = 0;
            foreach ($this->layers as $selector => $selectorLayers) {
                foreach ($selectorLayers as $layerKey => $layer) {
                    // Check dependencies ar satisfied
                    $depSatisfied = true;
                    foreach ($layer->getDependencies() as $dependency) {
                        if (!isset($dependency["selector"])) {
                            if (!isset($executed[$selector][$dependency["layer"]])) {
                                $depSatisfied = false;
                                break;
                            }
                        } else {
                            if (!isset($executed[$dependency["selector"]][$dependency["layer"]])) {
                                $depSatisfied = false;
                                break;
                            }
                        }
                    }

                    // execute layer if dependencies are satisfied and required parameters
                    if ($depSatisfied && $layer->getRequiredParametersSatisfied()) {
                        echo "Execute layer ".$layer->getName()."...\n";
                        if ($layer->executeScripts()) {
                            $layer->getConnection()->execute("INSERT INTO `_small_orm_layers` (`selector`, `layer`) VALUES(:selector, :layer);",
                                ["selector" => $selector, "layer" => $layer->getName()]);
                            $executed[$selector][$layer->getName()] = true;
                            unset($this->layers[$selector][$layerKey]);
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
        foreach ($this->layers as $selector => $selectorLayers) {
            foreach ($selectorLayers as $layer) {
                if($layer->getRequiredParametersSatisfied()) {
                    if (!$report) {
                        $report = true;
                        echo "LAYER EXECUTE REPORT\n";
                        echo "====================\n\n";
                        echo "Some layers have not be executed because unresolved dependencies :\n\n";
                    }

                    echo "-> Layer '" . $layer->getName() . "' of selector '" . $selector . "'\n";
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
            try {
                // Create database and layers tables if not exists
                $connection = $connectionsFactory->get($connectionName);
                $connection->execute("CREATE TABLE IF NOT EXISTS `_small_orm_layers` ( `id` INT NOT NULL AUTO_INCREMENT , `selector` VARCHAR(255) NOT NULL , `layer` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`)) ENGINE = InnoDB;");

                // List already executed layers
                $result = $connection->execute("SELECT `selector`, `layer` from `_small_orm_layers`");
                foreach ($result as $record) {
                    // And store them
                    if (!isset($executedLayers[$record["selector"]])) {
                        $executedLayers[$record["selector"]] = [];
                    }
                    $executedLayers[$record["selector"]][$record["layer"]] = true;
                }
            } catch (\Exception $e) {}
        }

        return $executedLayers;
    }
}
