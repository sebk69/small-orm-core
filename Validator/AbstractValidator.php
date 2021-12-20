<?php

/**
 * This file is a part of sebk/small-orm-core
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Validator;

use Sebk\SmallOrmCore\Factory\Dao;
use Sebk\SmallOrmCore\Dao\Model;

abstract class AbstractValidator
{
    protected $daoFactory;
    protected $model;
    protected $message;

    /**
     *
     * @param Dao $daoFactory
     * @param Model $model
     */
    public function __construct(Dao $daoFactory, Model $model)
    {
        $this->model      = $model;
        $this->daoFactory = $daoFactory;
    }

    /**
     *
     * @param type $property
     * @param type $table
     * @param type $idTable
     * @return type
     */
    /*public function testRelation($property, $table, $idTable)
    {
        $daoCible   = $this->factory->getDao($table);
        $whereArray = array(
            array(
                "modelFieldName" => $idTable,
                "operator" => "=",
                "valeur" => $this->model->$property,
            ),
        );
        $result     = $daoCible->select($whereArray);

        return count($result) == 1;
    }*/

    /**
     * Validation abstract
     */
    abstract public function validate();

    /**
     * Test if field is empty
     * @param string $field
     * @return boolean
     */
    public function testNonEmpty($field)
    {
        $method = "get".$field;
        if ($this->model->$method() !== null && trim($this->model->$method()) != "") {
            return true;
        }

        return false;
    }

    /**
     * Get errors message
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Test field is unique
     * @param string $field
     * @return bool
     */
    public function testUnique($field)
    {
        $dao      = $this->daoFactory->get($this->model->getBundle(),
            $this->model->getModelName());
        $creation = !$this->model->fromDb;

        $query  = $dao->createQueryBuilder("uniqueTable");
        $where  = $query->where();
        $method = "get".$field;

        if ($creation) {
            $result = $dao->findBy(array($field => $this->model->$method()));
        } else {
            $first = true;
            foreach ($this->model->getPrimaryKeys() as $key => $value) {
                if ($first) {
                    $where->firstCondition($query->getFieldForCondition($key),
                        "<>", ":".$key."Primary");
                    $query->setParameter($key."Primary", $value);
                } else {
                    $where->andCondition($query->getFieldForCondition($key),
                        "<>", ":".$key."Primary");
                    $query->setParameter($key."Primary", $value);
                }
            }

            $where->andCondition($query->getFieldForCondition($field), "=",
                ":".$field);
            $query->setParameter($field, $this->model->$method());

            $result = $dao->getResult($query);
        }

        return count($result) == 0;
    }

    /**
     * Test field is unique for determinant
     * @param $determinantField
     * @param $determinantValue
     * @param $field
     * @return bool
     * @throws \Sebk\SmallOrmCore\Factory\ConfigurationException
     * @throws \Sebk\SmallOrmCore\Factory\DaoNotFoundException
     */
    public function testUniqueWithDeterminant($determinantField, $determinantValue, $field)
    {
        $dao      = $this->daoFactory->get($this->model->getBundle(),
            $this->model->getModelName());
        $creation = !$this->model->fromDb;

        $query  = $dao->createQueryBuilder("uniqueTable");
        $where  = $query->where();
        $method = "get".$field;

        if ($creation) {
            $result = $dao->findBy(array($field => $this->model->$method(), $determinantField => $determinantValue));
        } else {
            $first = true;
            foreach ($this->model->getPrimaryKeys() as $key => $value) {
                if ($first) {
                    $where->firstCondition($query->getFieldForCondition($key),
                        "<>", ":".$key."Primary");
                    $query->setParameter($key."Primary", $value);
                } else {
                    $where->andCondition($query->getFieldForCondition($key),
                        "<>", ":".$key."Primary");
                    $query->setParameter($key."Primary", $value);
                }
            }

            $where->andCondition($query->getFieldForCondition($field), "=",
                ":".$field);
            $query->setParameter($field, $this->model->$method());

            $where->andCondition($query->getFieldForCondition($determinantField), "=", ":determinant");
            $query->setParameter("determinant", $determinantValue);

            $result = $dao->getResult($query);
        }

        return count($result) == 0;
    }

    public function testInteger($field)
    {
        $method = "get".$field;
        $value = $this->model->$method();
        $numbers = str_split("0123456789");
        for($i = 0; $i < strlen($value); $i++) {
            if(!in_array(substr($value, $i, 1), $numbers)) {
                return false;
            }
        }

        return true;
    }
}
