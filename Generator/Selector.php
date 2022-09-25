<?php

/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2022 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Generator;

class Selector
{

    protected array $daoConfig;
    protected array $modelConfig;

    public function __construct(array $folders, array $selectorConfig)
    {
        $this->daoConfig = $this->createConfig($folders, $selectorConfig['dao_namespace']);
        $this->modelConfig = $this->createConfig($folders, $selectorConfig['model_namespace']);
    }

    /**
     * Create config
     * @param array $folders
     * @param string $namespace
     * @return array
     * @throws \Exception
     */
    private function createConfig(array $folders, string $namespace): array
    {
        // Scan all config folders
        foreach ($folders as $baseNamespace => $folder) {
            // If namespace of folder match
            if (substr($namespace, 0, strlen($baseNamespace)) == $baseNamespace) {
                // Complete folder path for namespace
                $baseParts = explode('\\', $baseNamespace);
                $namespaceParts = explode('\\', $namespace);
                $finalFolderParts = explode('/', $folder);
                foreach ($namespaceParts as $i => $part) {
                    if (!array_key_exists($i, $baseParts) || $baseParts[$i] != $part) {
                        $finalFolderParts[] = $part;
                    }
                }

                // Return config
                return [
                    'namespace' => $namespace,
                    'folder' => implode('/', $finalFolderParts),
                ];
            }
        }

        // Not found
        throw new \Exception('Can\'t find generation location for namespace ' .$namespace);
    }

    /**
     * @return string
     */
    public function getDaoNamespace(): string
    {
        return $this->daoConfig['namespace'];
    }

    /**
     * @return string
     */
    public function getDaoFolder(): string
    {
        return $this->daoConfig['folder'];
    }

    /**
     * @return string
     */
    public function getModelNamespace(): string
    {
        return $this->modelConfig['namespace'];
    }

    /**
     * @return string
     */
    public function getModelFolder(): string
    {
        return $this->modelConfig['folder'];
    }

}