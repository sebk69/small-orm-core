<?php
/**
 * This file is a part of sebk/small-orm-core
 * Copyrightt 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Dao;

/**
 * Model base collection
 */
class ModelCollection implements \IteratorAggregate, \ArrayAccess, \JsonSerializable
{
    protected $objects = array();

    /**
     * @param \Sebk\SmallOrmCore\Dao\ModelCollection || array $array
     * @throws DaoException
     */
    public function __construct($array = array())
    {
        if ($array instanceof ModelCollection) {
            $this->objects = $array->objects;
        } elseif (is_array($array)) {
            foreach ($array as $key => $value) {
                if ($value instanceof Model || $value === null) {
                    $this->objects[$key] = $value;
                } else {
                    throw new DaoException("You can only add Sebk\\SmallOrmCore\\Dao\\Model objects to ModelCollection");
                }
            }
        } else {
            throw new DaoException("Sebk\\SmallOrmCore\\Dao\\ModelCollection constructor accept only array and ModelCollection parameter");
        }
    }

    /**
     * @param string $key
     * @return boolean
     */
    public function offsetExists($key)
    {
        if (array_key_exists($key, $this->objects)) {
            return true;
        }

        return false;
    }

    /**
     *
     * @param string $key
     * @return Model
     * @throws DaoException
     */
    public function offsetGet($key)
    {
        if (array_key_exists($key, $this->objects)) {
            return $this->objects[$key];
        }

        throw new DaoException("Offset '$key' doesn't exists");
    }

    /**
     *
     * @param string $key
     * @param \Sebk\SmallOrmCore\Dao\Model $value
     * @throws DaoException
     */
    public function offsetSet($key, $value)
    {
        if (is_null($key) && ($value instanceof Model || $value === null)) {
            $this->objects[] = $value;

            return;
        } elseif ($value instanceof Model || $value === null) {
            $this->objects[$key] = $value;

            return;
        }

        throw new DaoException("You can only add Sebk\\SmallOrmCore\\Dao\\Model objects to ModelCollection");
    }

    /**
     * @param string $key
     * @throws DaoException
     */
    public function offsetUnset($key)
    {
        if (array_key_exists($key, $this->objects)) {
            unset($this->objects[$key]);
        }

        throw new DaoException("Offset '$key' doesn't exists");
    }

    public function jsonSerialize() {
        $result = array();

        foreach($this->objects as $key => $value) {
            $result[] = $value->jsonSerialize();
        }

        return $result;
    }

    public function toArray() {
        $result = array();

        foreach($this->objects as $key => $value) {
            $result[] = $value->toArray();
        }

        return $result;
    }

    function getIterator()
    {
        return new \ArrayIterator($this->objects);
    }

    function count()
    {
        return count($this->objects);
    }
}
