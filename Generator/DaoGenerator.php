<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Generator;


use Psr\Container\ContainerInterface;
use Sebk\SmallOrmCore\Dao\AbstractDao;
use Sebk\SmallOrmCore\Dao\DaoException;
use Sebk\SmallOrmCore\Dao\Field;
use Sebk\SmallOrmCore\Dao\ToOneRelation;
use Sebk\SmallOrmCore\Database\AbstractConnection;
use Sebk\SmallOrmCore\Factory\Connections;
use Sebk\SmallOrmCore\Factory\Dao;
use Sebk\SmallOrmCore\Factory\DaoNotFoundException;
use Sebk\SmallOrmCore\Generator\Bean\DaoFile;
use Sebk\SmallOrmCore\Generator\Bean\ModelFile;

/**
 * Class DaoGenerator
 * @package Sebk\SmallOrmCore\Generator
 */
class DaoGenerator
{
    protected string $connectionName;
    protected DbGateway $dbGateway;
    protected Selector $selector;
    /** @var Selector[] */
    protected array $selectors;
    protected DaoFile $daoFile;
    protected ModelFile $modelFile;

    /**
     * DaoGenerator constructor.
     */
    public function __construct(protected Connections $connections, protected ContainerInterface $container, protected array $config) {
        $this->first = true;
        foreach ($this->config['selectors'] as $selectorArray) {
            $this->selectors[] = new Selector($this->config['folders'], $selectorArray);
        }
    }

    /**
     * Set class parameters
     * @param string $connectionName
     * @param Selector $selector
     * @return $this
     * @throws \Sebk\SmallOrmCore\Factory\ConfigurationException
     */
    public function setParameters(string $connectionName, Selector $selector): DaoGenerator
    {
        $this->connectionName = $connectionName;
        $this->dbGateway = new DbGateway($this->connections->get($connectionName));
        $this->selector = $selector;
        if (!is_dir($selector->getDaoFolder())) {
            mkdir($selector->getDaoFolder(), 0777, true);
        } else {
            $this->first = false;
        }
        if (!is_dir($selector->getModelFolder())) {
            mkdir($selector->getModelFolder(), 0777, true);
        }

        return $this;
    }

    /**
     * recompute files for a table
     */
    public function recomputeFilesForTable(string $dbTableName, bool $replace = false)
    {
        // Build function for the DAO
        $daoFile = new DaoFile(
            $dbTableName,
            $this->selector,
            $this->selectors,
            $this->config["remove_tables_namespaces"],
            $this->dbGateway
        );
        $content = $daoFile->getFileContent();
        $parser = new FileParser($content);
        $buildPos = $parser->findFunctionPos("build");
        $content = substr_replace($content, $daoFile->generateBuildFunction(), $buildPos["start"], $buildPos["end"] - $buildPos["start"]);
        $daoFile->putFileContent($content);

        // Create model class if not exists
        $modelFile = new ModelFile(
            $dbTableName,
            $this->selector,
            $this->selectors,
            $this->config["remove_tables_namespaces"],
            $this->dbGateway
        );
        if(!$modelFile->exists()) {
            $content = $modelFile->getTemplate();
            $content = str_replace("[uses]", $modelFile->generateUses(), $content);
            $content = str_replace("[getters]", $modelFile->generateGetters(), $content);
            $content = str_replace("[setters]", $modelFile->generateSetters(), $content);
            $modelFile->putFileContent($content);
        }

        return $this;
    }

}
