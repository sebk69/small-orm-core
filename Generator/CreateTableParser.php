<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Generator;


use Sebk\SmallOrmCore\Database\ConnectionMysql;

class CreateTableParser
{
    protected $connection;
    protected $createTableString;
    protected $relations = [];

    public function __construct(ConnectionMysql $connection)
    {
        $this->connection = $connection;
    }

    public function openTable($dbTableName)
    {
        $result = $this->connection->execute("show create table `".$dbTableName."`");

        if(isset($result[0]["Create Table"])) {
            $this->createTableString = $result[0]["Create Table"];
        } else {
            throw new \Exception("Table ".$dbTableName." doesn't exists");
        }
    }

    public function parseForeignKeys($start = 0)
    {
        if($this->createTableString === null) {
            throw new \Exception("No table opened for parsing");
        }
        $createTableString = $this->createTableString;

        $i = strpos($createTableString, "CONSTRAINT", $start);

        if($i === false) {
            return $this;
        }

        $startRelationName = strpos($createTableString, "`", $i) + 1;
        $endRelationName = strpos($createTableString, "`", $startRelationName + 1);
        $relation["relation"] = substr($createTableString, $startRelationName, $endRelationName - $startRelationName);

        $startToField = strpos($createTableString, "`", $endRelationName + 1) + 1;
        $endToField = strpos($createTableString, "`", $startToField + 1);
        $relation["toField"] = substr($createTableString, $startToField, $endToField - $startToField);

        $startToField = strpos($createTableString, "`", $endRelationName + 1) + 1;
        $endToField = strpos($createTableString, "`", $startToField + 1);
        $relation["toField"] = substr($createTableString, $startToField, $endToField - $startToField);

        $startToTable = strpos($createTableString, "`", $endToField + 1) + 1;
        $endToTable = strpos($createTableString, "`", $startToTable + 1);
        $relation["toTable"] = substr($createTableString, $startToTable, $endToTable - $startToTable);

        $startFromField = strpos($createTableString, "`", $endToTable + 1) + 1;
        $endFromField = strpos($createTableString, "`", $startFromField + 1);
        $relation["fromField"] = substr($createTableString, $startFromField, $endFromField - $startFromField);

        $this->relations[] = $relation;

        return $this->parseForeignKeys($endFromField);
    }

    public function getRelations()
    {
        return $this->relations;
    }
}
