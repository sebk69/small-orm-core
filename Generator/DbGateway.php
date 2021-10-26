<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Generator;


use Sebk\SmallOrmCore\Database\AbstractConnection;

class DbGateway
{
    protected $connection;
    protected $loaded = false;
    protected $dbTables = [];
    protected $toOnes = [];
    protected $toManys = [];
    protected $tableDescriptions = [];

    /**
     * DbGateway constructor.
     * @param AbstractConnection $connection
     */
    public function __construct(AbstractConnection $connection)
    {
        $this->connection = $connection;
    }

    protected function load()
    {
        if (!$this->loaded) {
            $this->loaded = true;

            // build tables list
            $dbTables = $connection->execute("show tables");
            foreach ($dbTables as $record) {
                foreach ($record as $table) {
                    // foreach table
                    $this->dbTables[] = $table;

                    // get description
                    $this->tableDescriptions[$table] = $connection->execute("describe `".$table."`");

                    // get to one relations
                    $parser = new CreateTableParser($connection);
                    $parser->openTable($table);
                    $parser->parseForeignKeys();
                    $this->toOnes[$table] = $parser->getRelations();

                    // impact on to many
                    foreach ($this->toOnes[$table] as $toOneRelation) {
                        $toManyRelation = [
                            "toTable" => $table,
                            "fromField" => $toOneRelation["toField"],
                            "toField" => $toOneRelation["fromField"],
                        ];

                        if(!isset($this->toMany[$toOneRelation["toTable"]])) {
                            $this->toMany[$toOneRelation["toTable"]] = [];
                        }

                        $this->toMany[$toOneRelation["toTable"]][] = $toManyRelation;
                    }
                }
            }
        }
    }

    /**
     * Return all connection tables
     * @return array
     */
    public function getTables()
    {
        $this->load();
        return $this->dbTables;
    }

    /**
     * Return to one relations for a table
     * @param $table
     * @return array
     */
    public function getToOnes($table)
    {
        $this->load();
        if(isset($this->toOnes[$table])) {
            return $this->toOnes[$table];
        }

        return [];
    }

    /**
     * Return to many relations for a table
     * @param $table
     * @return array|mixed
     */
    public function getToManys($table)
    {
        $this->load();
        if(isset($this->toMany[$table])) {
            return $this->toMany[$table];
        }

        return [];
    }

    public function getDescription($table)
    {
        $this->load();
        if(isset($this->tableDescriptions[$table])) {
            return $this->tableDescriptions[$table];
        }

        throw new \Exception("Table $table not found in database");
    }
}
