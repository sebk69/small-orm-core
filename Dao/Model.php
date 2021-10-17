<?php

/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Dao;

use Sebk\SmallOrmCore\Validator\AbstractValidator;

/**
 * Class model
 */
class Model implements \JsonSerializable {

    const FIELD_NOT_PERSIST = "FIELD_NOT_PERSIST";
    const MYSQL_FORMAT_DATETIME = "Y-m-d H:i:s";

    private $modelName;
    private $bundle;
    protected $container;
    protected $validator;
    private $primaryKeys = array();
    private $originalPrimaryKeys = null;
    private $fields = array();
    private $types = array();
    private $toOnes = array();
    private $toManys = array();
    private $metadata = array();
    public $fromDb = false;
    public $altered = false;
    private $backup = null;

    /**
     * Construct model
     * @param string $modelName
     * @param array $primaryKeys
     * @param array $fields
     */
    public function __construct($modelName, $bundle, $primaryKeys, $fields, $types, $toOnes, $toManys, $container)
    {
        $this->modelName = $modelName;
        $this->bundle = $bundle;
        $this->container = $container;

        foreach ($primaryKeys as $primaryKey) {
            $this->primaryKeys[$primaryKey] = null;
        }

        foreach ($fields as $i => $field) {
            $this->fields[$field] = null;
            $this->types[$field] = $types[$i];
        }

        foreach ($toOnes as $toOne) {
            $this->toOnes[$toOne] = null;
        }

        foreach ($toManys as $toMany) {
            $this->toManys[$toMany] = null;
        }
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->modelName;
    }

    /**
     * @return string
     */
    public function getBundle()
    {
        return $this->bundle;
    }

    /**
     * Magic method to access getters and setters
     * @param $method
     * @param $args
     * @return $this|mixed
     * @throws ModelException
     */
    public function __call($method, $args)
    {
        $type = substr($method, 0, 3);
        $name = lcfirst(substr($method, 3));
        $typeField = $this->getFieldType($name);

        switch ($type) {
            case "get":
                if ($typeField == "primaryKeys") {
                    return $this->primaryKeys[$name];
                } elseif ($typeField == "field") {
                    switch($this->types[$name]["type"]) {
                        case Field::TYPE_STRING:
                        case Field::TYPE_NUMBER:
                            return $this->fields[$name];
                        case Field::TYPE_BOOLEAN:
                            if($this->fields[$name] !== null) {
                                return $this->fields[$name] == $this->types[$name]["format"][1] ? true : false;
                            } else {
                                return null;
                            }
                        case Field::TYPE_DATETIME:
                            if($this->fields[$name] !== null) {
                                return \DateTime::createFromFormat($this->types[$name]["format"], $this->fields[$name]);
                            } else {
                                return null;
                            }
                    }
                    return $this->fields[$name];
                } elseif ($typeField == "toOne") {
                    return $this->toOnes[$name];
                } elseif ($typeField == "toMany") {
                    return $this->toManys[$name];
                } elseif ($typeField == "metadata" && array_key_exists($name, $this->metadata)) {
                    return $this->metadata[$name];
                }
                throw new ModelException("Method '$method' does not exists");
                break;
            case "set":
                if ($typeField == "primaryKeys") {
                    $this->primaryKeys[$name] = $args[0];
                } elseif ($typeField == "field") {
                    switch($this->types[$name]["type"]) {
                        case Field::TYPE_STRING:
                        case Field::TYPE_NUMBER:
                            $this->fields[$name] = $args[0];
                            break;
                        case Field::TYPE_BOOLEAN:
                            if($args[0] !== null) {
                                $this->fields[$name] = $args[0] ? $this->types[$name]["format"][1] : $this->types[$name]["format"][0];
                            } else {
                                $this->fields[$name] = null;
                            }
                            break;
                        case Field::TYPE_DATETIME:
                            if($args[0] !== null) {
                                $this->fields[$name] = $args[0]->format(static::MYSQL_FORMAT_DATETIME);
                            } else {
                                $this->fields[$name] = null;
                            }
                            break;
                    }

                } elseif ($typeField == "toOne") {
                    $this->toOnes[$name] = $args[0];
                } elseif ($typeField == "toMany") {
                    $this->toManys[$name] = $args[0];
                } elseif ($typeField == "metadata") {
                    $this->metadata[$name] = $args[0];
                }
                return $this;
                break;
            case "raw":
                if ($typeField == "primaryKeys") {
                    $this->primaryKeys[$name] = $args[0];
                } elseif ($typeField == "field") {
                    $this->fields[$name] = $args[0];
                } elseif ($typeField == "toOne") {
                    $this->toOnes[$name] = $args[0];
                } elseif ($typeField == "toMany") {
                    $this->toManys[$name] = $args[0];
                } elseif ($typeField == "metadata") {
                    $this->metadata[$name] = $args[0];
                }
                return $this;
                break;
            default:
                throw new ModelException("Method '$method' doesn't extist in model '$this->modelName' of bundle '$this->bundle'");
        }
    }

    /**
     * Magic getter
     * @param $property
     * @return mixed
     * @throws ModelException
     */
    public function __get($property)
    {
        $typeField = $this->getFieldType($property);

        if ($typeField == "primaryKeys") {
            return $this->primaryKeys[$property];
        } elseif ($typeField == "field") {
            return $this->fields[$property];
        } elseif ($typeField == "toOne") {
            return $this->toOnes[$property];
        } elseif ($typeField == "toMany") {
            return $this->toManys[$property];
        } elseif ($typeField == "metadata" && array_key_exists($property, $this->metadata)) {
            return $this->metadata[$property];
        }

        throw new ModelException("Property '$property' does not exists");
    }

    /**
     * Magic setter
     * @param $property
     * @param $value
     */
    public function __set($property, $value)
    {
        $typeField = $this->getFieldType($property);

        if ($typeField == "primaryKeys") {
            $this->primaryKeys[$property] = $value;
        } elseif ($typeField == "field") {
            $this->fields[$property] = $value;
        } elseif ($typeField == "toOne") {
            $this->toOnes[$property] = $value;
        } elseif ($typeField == "toMany") {
            $this->toManys[$property] = $value;
        } elseif ($typeField == "metadata") {
            $this->metadata[$property] = $value;
        }
    }

    /**
     * Set original primary key
     */
    public function setOriginalPrimaryKeys()
    {
        $this->originalPrimaryKeys = $this->primaryKeys;
    }

    /**
     * Get original primary key
     * @return string
     */
    public function getOriginalPrimaryKeys()
    {
        return $this->originalPrimaryKeys;
    }

    /**
     * Get field type
     * @param string $field
     * @return string
     * @throws \ModelException
     */
    public function getFieldType($field)
    {
        if (array_key_exists($field, $this->primaryKeys)) {
            return "primaryKeys";
        }

        if (array_key_exists($field, $this->fields)) {
            return "field";
        }

        if (array_key_exists($field, $this->toOnes)) {
            return "toOne";
        }

        if (array_key_exists($field, $this->toManys)) {
            return "toMany";
        }

        return "metadata";
    }

    /**
     * Get list of primary keys
     * @return array
     */
    public function getPrimaryKeys()
    {
        return $this->primaryKeys;
    }

    /**
     * Convert model to array
     * @param boolean $dependecies
     * @return array
     */
    public function toArray($dependecies = true, $onlyFields = false, $fromJsonSeriaze = false)
    {
        $result = array();

        foreach ($this->primaryKeys as $key => $value) {
            if ($value !== null) {
                $result[$key] = (float)$value;
            } else {
                $result[$key] = null;
            }
        }

        foreach ($this->fields as $key => $value) {
            if ($value !== null) {
                switch ($this->types[$key]["type"]) {
                    case Field::TYPE_STRING:
                        $result[$key] = $value;
                        break;
                    case Field::TYPE_NUMBER:
                        $result[$key] = (float)$value;
                        break;
                    case Field::TYPE_BOOLEAN:
                        if($value !== null) {
                            $result[$key] = $value == $this->types[$key]["format"][1] ? true : false;
                        } else {
                            $result[$key] = null;
                        }
                        break;
                    case Field::TYPE_DATETIME:
                        if($value !== null) {
                            $date = \DateTime::createFromFormat(self::MYSQL_FORMAT_DATETIME, $value);
                            $result[$key] = $date->format($this->types[$key]["format"]);
                        } else {
                            $result[$key] = null;
                        }
                        break;
                }
            } else {
                $result[$key] = null;
            }
        }

        if ($dependecies && !$fromJsonSeriaze) {
            foreach ($this->toOnes as $key => $model) {
                if ($model !== null) {
                    $result[$key] = $model->toArray($dependecies, $onlyFields);
                } else {
                    $result[$key] = null;
                }
            }

            foreach ($this->toManys as $key => $array) {
                if ($array !== null) {
                    $result[$key] = array();
                    foreach ($array as $i => $model) {
                        if ($model !== null && $model instanceof Model) {
                            $result[$key][] = $model->toArray($dependecies, $onlyFields);
                        } elseif ($model !== null) {
                            $result[$key][] = $model;
                        } else {
                            $result[$key][] = null;
                        }
                    }
                } else {
                    $result[$key] = array();
                }
            }
        }

        if ($dependecies && $fromJsonSeriaze) {
            foreach ($this->toOnes as $key => $model) {
                if ($model !== null) {
                    $result[$key] = $model->jsonSerialize($dependecies, $onlyFields);
                } else {
                    $result[$key] = null;
                }
            }

            foreach ($this->toManys as $key => $array) {
                if ($array !== null) {
                    $result[$key] = array();
                    foreach ($array as $i => $model) {
                        if ($model !== null && $model instanceof Model) {
                            $result[$key][] = $model->jsonSerialize($dependecies, $onlyFields);
                        } elseif ($model !== null) {
                            $result[$key][] = $model;
                        } else {
                            $result[$key][] = null;
                        }
                    }
                } else {
                    $result[$key] = array();
                }
            }
        }

        if (!$onlyFields) {
            foreach ($this->metadata as $key => $value) {
                if ($value instanceof ModelCollection || $value instanceof Model) {
                    $result[$key] = $value->toArray();
                } else {
                    $result[$key] = $value;
                }
            }

            $result["fromDb"] = $this->fromDb;

            if ($this->backup !== null) {
                $result["backup"] = get_object_vars($this->backup);
            }
        }

        return $result;
    }

    /**
     * Json serialisation of model
     * @return array
     */
    public function jsonSerialize()
    {
        if (is_array($this->toArray(true, false, true))) {
            return $this->toUtf8Array($this->toArray(true, false, true));
        } else {
            return $this->toArray(false, false, true);
        }
    }

    /**
     * Serialize model to an array (convert strings to utf8)
     * @param array $array
     * @return array
     */
    protected function toUtf8Array($array)
    {
        foreach ($array as $key => $cell) {
            if (is_array($cell)) {
                $array[$key] = $this->toUtf8Array($cell);
            } elseif (!is_object($cell)) {
                $array[$key] = $this->toUtf8String($cell);
            } elseif ($cell instanceof Model) {
                $array[$key] = $this->toUtf8Array($cell->toArray());
            } else {
                $array[$key] = $this->toUtf8Array((array) $cell);
            }
        }

        return $array;
    }

    /**
     * Convert a string to utf8 if necessary
     * @param string $str
     * @return string
     */
    protected function toUtf8String($str)
    {
        if (mb_detect_encoding($str, 'UTF-8', true) === false) {
            return utf8_encode($str);
        }

        return $str;
    }

    /**
     * Load a toOne relation if not loaded
     * @param $alias
     * @param array $dependenciesAliases
     * @return Model
     * @throws DaoException
     */
    public function loadToOne($alias, $dependenciesAliases = array())
    {
        if (!array_key_exists($alias, $this->toOnes)) {
            throw new DaoException("Field '$alias' does not exists (loading to one relation");
        }

        if ($this->toOnes[$alias] === null) {
            $this->container
                    ->get("sebk_small_orm_dao")
                    ->get($this->bundle, $this->modelName)
                    ->loadToOne($alias, $this, $dependenciesAliases);
        }

        return $this->toOnes[$alias];
    }

    /**
     * Load a toMany relation if not loaded
     * @param $alias
     * @param array $dependenciesAliases
     * @return Array
     * @throws DaoException
     */
    public function loadToMany($alias, $dependenciesAliases = array())
    {
        if (!array_key_exists($alias, $this->toManys)) {
            throw new DaoException("Field '$alias' does not exists (loading to many relation)");
        }
        if ($this->toManys[$alias] === null || count($this->toManys[$alias]) == 0) {
            $this->container
                    ->get("sebk_small_orm_dao")
                    ->get($this->bundle, $this->modelName)
                    ->loadToMany($alias, $this, $dependenciesAliases);
        }

        return $this->toManys[$alias];
    }

    /**
     * Get the DAO of model
     * @return mixed
     */
    public function getDao()
    {
        return $this->container
                    ->get("sebk_small_orm_dao")
                    ->get($this->bundle, $this->modelName);
    }

    /**
     * Persist this model
     * @return $this
     */
    public function persist()
    {
        $this->getDao()->persist($this);

        return $this;
    }

    /**
     * Delete this model
     * @return $this
     */
    public function delete()
    {
        $this->getDao()->delete($this);

        return $this;
    }

    /**
     * Get validator
     * @return AbstractValidator
     */
    public function getValidator()
    {
        if($this->validator === null) {
            $this->validator = $this->container->get("sebk_small_orm_validator")->get($this);
        }

        return $this->validator;
    }

    /**
     * Backup values of model (also metadata)
     * @param bool $deeply
     * @param bool $dry
     * @return mixed
     */
    public function backup($deeply = false, $dry = false)
    {
        // save object
        $json = json_encode($this->toArray(false));
        $backup = json_decode($json);

        if(!$dry) {
            if(isset($backup->backup)) {
                unset($backup->backup);
            }

            $this->backup = $backup;

            // save dependencies
            if($deeply) {
                foreach ($this->toOnes as $key => $model) {
                    if($model !== null) {
                        $model->backup();
                    }
                }

                foreach ($this->toManys as $key => $array) {
                    if ($array !== null) {
                        foreach ($array as $model) {
                            $model->backup();
                        }
                    }
                }
            }
        }

        return $backup;
    }

    /**
     * Get backup
     * @return null
     * @throws ModelException
     */
    public function getBackup()
    {
        if(!is_object($this->backup)) {
            throw new ModelException("No backup to get");
        }

        return $this->backup;
    }

    /**
     * Manually set backup
     * @param $backup
     * @return $this
     * @throws ModelException
     */
    public function setBackup($backup)
    {
        if(!($backup instanceof \stdClass)) {
            throw new ModelException("Backup data must be in stdClass");
        }

        $this->backup = $backup;

        return $this;
    }

    /**
     * Test if object modified since last backup
     * @return bool
     * @throws ModelException
     */
    public function modifiedSinceBackup()
    {
        if(!isset($this->backup)) {
            throw new ModelException("Backup is not set");
        }

        $newBackup = $this->backup(false, true);

        return $this->backup == $newBackup;
    }
}
