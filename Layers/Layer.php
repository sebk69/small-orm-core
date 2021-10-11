<?php
/**
 * This file is a part of SebkSmallOrmCore
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Layers;


use Sebk\SmallOrmCore\Factory\Connections;
use Symfony\Component\Yaml\Yaml;

class Layer
{
    // Allowed parameters
    const CONNECTION_PARAMETER = "connection";
    const DEPENDS_PARAMETER = "depends";
    const REQUIRED_PARAMETER = "required-parameters";
    const NO_EXECUTE_PARAMETER = "no-execute";

    // Properties
    protected $layerRootPath;
    protected $layerName;
    protected $connectionsFactory;
    protected $configFilePath;
    protected $connection;
    protected $dependencies = [];
    protected $container;
    protected $requiredParametersSatisfied;

    /**
     * Layer constructor.
     * @param $layerRootPath
     * @param $layerName
     */
    public function __construct($layerRootPath, $layerName, Connections $connectionsFactory, $container)
    {
        $this->layerRootPath = $layerRootPath;
        $this->layerName = $layerName;
        $this->connectionsFactory = $connectionsFactory;
        $this->container = $container;
        $this->configFilePath = $this->layerRootPath."/".$this->layerName."/config.yml";
        $this->loadLayer();
    }

    /**
     * Get layer name
     * @return mixed
     */
    public function getName()
    {
        return $this->layerName;
    }

    /**
     * Get dependencies
     * @return mixed
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    /**
     * Get connection
     * @return mixed
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get if required satisfied.
     * If the config is not loaded, return null
     * @return mixed
     */
    public function getRequiredParametersSatisfied()
    {
        return $this->requiredParametersSatisfied;
    }

    /**
     * Load layer :
     * - config
     * - scripts
     * TODO - fixtures
     */
    protected function loadLayer()
    {
        // Load the config
        $this->loadConfig();
    }

    /**
     * Load config in class
     * @throws LayerConfigNotFoundException
     */
    protected function loadConfig()
    {
        // Check config file
        if(!file_exists($this->configFilePath)) {
            throw new LayerConfigNotFoundException("The config file of layer ".$this->layerName." can't be found. It should be in '".$this->configFilePath."'");
        }

        // Parse config file
        $config = Yaml::parse(file_get_contents($this->configFilePath));

        // Check syntax of config
        $this->checkConfigSyntax($config);

        // Load values
        $this->connection = $this->connectionsFactory->get($config[static::CONNECTION_PARAMETER]);
        if(isset($config[static::DEPENDS_PARAMETER])) {
            $rawDependencies = $config[static::DEPENDS_PARAMETER];
            foreach ($rawDependencies as $rawDependance) {
                if(strstr($rawDependance, "@")) {
                    // not in same bundle
                    $exploded = explode("@", $rawDependance);
                    $this->dependencies[] = ["bundle" => $exploded[1], "layer" => $exploded[0]];
                } else {
                    // same bundle
                    $this->dependencies[] = ["layer" => $rawDependance];
                }
            }
        }

        $this->requiredParametersSatisfied = true;
        if(isset($config[static::REQUIRED_PARAMETER])) {
            foreach ($config[static::REQUIRED_PARAMETER] as $parameter => $value) {
                try {
                    $realValue = $this->container->getParameter($parameter);
                } catch(\Exception $e) {
                    throw new LayerUnknownParameter("The parameter '$parameter' not found in parameters.yml");
                }

                if($realValue != $value) {
                    $this->requiredParametersSatisfied = false;
                }
            }
        }

        if(isset($config[static::NO_EXECUTE_PARAMETER]) && $config[static::NO_EXECUTE_PARAMETER]) {
            $this->requiredParametersSatisfied = false;
        }
    }

    /**
     * Check config syntax
     * @param $config
     * @throws LayerConnectionNotFoundException
     * @throws LayerSyntaxError
     * @throws LayerUnknownParameter
     */
    protected function checkConfigSyntax($config)
    {
        // initialize common errors messages
        $layerPath = " in '".$this->configFilePath."'";

        // Initialize require parameters
        $connectionFound = false;

        // Foreach config parameters
        foreach($config as $configParameter => $configValue) {
            switch($configParameter) {
                // Connection found
                case static::CONNECTION_PARAMETER:
                    $connectionFound = true;
                    break;

                // Check depends parameter
                case static::DEPENDS_PARAMETER:
                    if(!is_array($configValue)) {
                        throw new LayerSyntaxError("The value for '".static::DEPENDS_PARAMETER."' parameter must be an array$layerPath");
                    }
                    break;

                // Check required parameters
                case static::REQUIRED_PARAMETER:
                    if(!is_array($configValue)) {
                        throw new LayerSyntaxError("Missing entries in paramter '".static::REQUIRED_PARAMETER."'$layerPath");
                    }
                    break;

                // Check no-execute parameter
                case static::NO_EXECUTE_PARAMETER:
                    if(!is_bool($configValue)) {
                        throw new LayerSyntaxError("The parameter '".static::NO_EXECUTE_PARAMETER."' must be boolean$layerPath");
                    }
                    break;

                // Unknown parameter
                default:
                    throw new LayerUnknownParameter("Parameter '".$configParameter."' is not valid$layerPath");
            }
        }

        // Check require parameters
        if(!$connectionFound) {
            throw new LayerConnectionNotFoundException("You must configure connection$layerPath");
        }
    }

    /**
     * Execute scripts
     * @return bool
     */
    public function executeScripts()
    {
        // Get scripts directory path
        $scriptsPath = $this->layerRootPath."/".$this->getName()."/scripts";
        
        // scan script directory
        $scriptsDir = scandir($scriptsPath);
        foreach($scriptsDir as $scriptFilename) {
            if(substr($scriptFilename, 0, 1) != ".") {
                // if file is not hidden, execute it
                $sql = file_get_contents($scriptsPath . "/" . $scriptFilename);
                echo "Execute script : ".$scriptFilename."\n";

                $singles = explode(";", $sql);
                foreach ($singles as $single) {
                    if(trim($single) != "") {
                        $status = $this->connection->execute($single);

                        if ($status === false) {
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }
}