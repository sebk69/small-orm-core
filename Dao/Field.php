<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Dao;

/**
 * Model field
 */
class Field
{
    const TYPE_STRING = "TYPE_STRING";
    const TYPE_PHP_FILTER = "TYPE_PHP_FILTER";
    const TYPE_DATETIME = "TYPE_DATETIME";
    const TYPE_DATE = "TYPE_DATE";
    const TYPE_BOOLEAN = "TYPE_BOOLEAN";
    const TYPE_FLOAT = "TYPE_FLOAT";
    const TYPE_INT = "TYPE_INT";
    const TYPE_TIMESTAMP = "TYPE_TIMESTAMP";
    const TYPE_JSON = "TYPE_JSON";

    protected $dbName;
    protected $modelName;
    protected $type = "TYPE_STRING";
    protected $format = null;

    /**
     *
     * @param string $dbName
     * @param string $modelName
     */
    public function __construct($dbName, $modelName)
    {
        $this->dbName = $dbName;
        $this->modelName = $modelName;
    }

    /**
     * @return string
     */
    public function getDbName()
    {
        return "`".$this->dbName."`";
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->modelName;
    }

    /**
     * Set type
     * @param $type
     * @param null $format
     * @throws \Exception
     */
    public function setType($type, $format = null)
    {
        switch($type) {
            case static::TYPE_STRING:
            case static::TYPE_FLOAT:
            case static::TYPE_INT:
                break;
            case static::TYPE_BOOLEAN:
                // Default format
                if($format === null) {
                    $format = ["0", "1"];
                }
                // checks
                if(!is_array($format)) {
                    throw new \Exception("Format must be array for boolean fields (field '$this->modelName')");
                }
                if(!isset($format[0]) || !isset($format[1])) {
                    throw new \Exception("Malformed format for field '$this->modelName'");
                }
                // set format
                $this->format = $format;
                break;
            case static::TYPE_TIMESTAMP:
            case static::TYPE_DATETIME:
                // Default format
                if($format === null) {
                    $format = "Y-m-d H:i:s";
                }
                // check format
                try {
                    $date = new \DateTime();
                    $date->format($format);
                } catch (\Exception $e) {
                    throw new \Exception("Malformed format for field '$this->modelName'");
                }
                // set format
                $this->format = $format;
                break;
            case static::TYPE_DATE:
                // Default format
                if($format === null) {
                    $format = "Y-m-d";
                }
                // check format
                try {
                    $date = new \DateTime();
                    $date->format($format);
                } catch (\Exception $e) {
                    throw new \Exception("Malformed format for field '$this->modelName'");
                }
                // set format
                $this->format = $format;
                break;
            case static::TYPE_PHP_FILTER:
                if (!in_array($format, [
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_VALIDATE_BOOL,
                    FILTER_VALIDATE_DOMAIN,
                    FILTER_VALIDATE_EMAIL,
                    FILTER_VALIDATE_FLOAT,
                    FILTER_VALIDATE_INT,
                    FILTER_VALIDATE_IP,
                    FILTER_VALIDATE_MAC,
                    FILTER_VALIDATE_REGEXP,
                    FILTER_VALIDATE_URL,
                ])) {
                    throw new \Exception("Malformed format for field '$this->modelName'");
                }

                // set format
                $this->format = $format;
                break;
            case static::TYPE_JSON:
                if (is_bool($format)) {
                    $this->format = $format;
                } else {
                    $this->format = true;
                }
                break;
            default:
                throw new \Exception("Unkown type '$type' for field '$this->modelName'");
        }

        $this->type = $type;
    }

    /**
     * Get type
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get format
     * @return null|array|string
     */
    public function getFormat()
    {
        return $this->format;
    }
}
