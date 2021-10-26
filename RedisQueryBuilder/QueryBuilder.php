<?php

namespace Sebk\SmallOrmCore\RedisQueryBuilder;

use Sebk\SmallOrmCore\Dao\AbstractRedisDao;
use Sebk\SmallOrmCore\Dao\Model;

class QueryBuilder
{

    /**
     * @var string
     */
    protected $instruction;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var AbstractRedisDao
     */
    protected $dao;

    /**
     * Create QueryBuilder
     * @param $dao
     */
    public function __construct(AbstractRedisDao $dao)
    {
        $this->dao = $dao;
        $this->params["key"] = [];
        $this->params["value"] = [];
    }

    /**
     * Get instruction
     * @return string
     */
    public function getInstruction()
    {
        return $this->instruction;
    }

    /**
     * Get parameters
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Get a key
     * @param $key
     * @return $this
     * @throws \Exception
     */
    public function get($key)
    {
        // Check syntax
        if ($this->instruction != null && $this->instruction != "get") {
            throw new \Exception("You can't use differents instructions in a same query !");
        }

        // Set instruction for connection object
        $this->instruction = "get";

        if ($key != "") {
            // If no key append key to dbTableName
            $fullkey = $this->dao->getDbTableName() . ":" . $key;
        } else {
            // else get dbTableName as key
            $fullkey = $this->dao->getDbTableName();
        }

        // Set parameter
        $this->params["key"][] = $fullkey;

        return $this;
    }

    /**
     * Set a key
     * @param string $key
     * @param Model $value
     * @return $this
     * @throws \Exception
     */
    public function set(string $key = null, Model $value)
    {
        // Check syntax
        if ($this->instruction != null && $this->instruction != "set") {
            throw new \Exception("You can't use differents instructions in a same query !");
        }
        
        // Get key if null
        if ($key === null) {
            $key = $value->getKey();
        }

        $this->instruction = "set";

        if ($key != "") {
            // If no key append key to dbTableName
            $fullkey = $this->dao->getDbTableName() . ":" . $key;
        } else {
            // else get dbTableName as key
            $fullkey = $this->dao->getDbTableName();
        }
        $value->setKey($key);

        // Set parameter
        $this->params["key"][] = $fullkey;
        $this->params["value"][] = $value;

        return $this;
    }

    /**
     * Remove a key
     * @param string $key
     * @return $this
     * @throws \Exception
     */
    public function del(string $key)
    {
        // Check syntax
        if ($this->instruction != null && $this->instruction != "del") {
            throw new \Exception("You can't use differents instructions in a same query !");
        }
        
        $this->instruction = "del";
        
        if ($key != "") {
            // If no key append key to dbTableName
            $fullkey = $this->dao->getDbTableName() . ":" . $key;
        } else {
            // else get dbTableName as key
            $fullkey = $this->dao->getDbTableName();
        }
        $this->params["key"][] = $fullkey;
        
        return $this;
    }

}