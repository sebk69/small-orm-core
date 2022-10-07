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
class ModelCollection implements \IteratorAggregate, \ArrayAccess, \JsonSerializable, \Countable
{

    /** @var Model[] */
    protected array $objects = [];

    /**
     * @param array $array
     * @throws DaoException
     */
    public function __construct(array $array = [])
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
     * Check if offset exists
     * @param mixed $key
     * @return bool
     */
    public function offsetExists(mixed $key): bool
    {
        if (array_key_exists($key, $this->objects)) {
            return true;
        }

        return false;
    }

    /**
     * Get object at offset $key
     * @param string $key
     * @return Model
     * @throws DaoException
     */
    public function offsetGet($key): mixed
    {
        if (array_key_exists($key, $this->objects)) {
            return $this->objects[$key];
        }

        throw new DaoException("Offset '$key' doesn't exists");
    }

    /**
     * Set object at offset $key
     * @param string $key
     * @param Model $value
     * @throws DaoException
     */
    public function offsetSet(mixed $key, mixed $value): void
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
     * Unset object at offset $offset
     * @param string $key
     * @throws DaoException
     */
    public function offsetUnset(mixed $offset): void
    {
        if (array_key_exists($offset, $this->objects)) {
            unset($this->objects[$offset]);
        }

        throw new DaoException("Offset '$offset' doesn't exists");
    }

    /**
     * Serialize the collection
     * @return mixed
     */
    public function jsonSerialize(): mixed {
        $result = array();

        foreach($this->objects as $key => $value) {
            $result[] = $value->jsonSerialize();
        }

        return $result;
    }

    /**
     * Convert collection to array
     * @return array
     */
    public function toArray(): array {
        $result = array();

        foreach($this->objects as $key => $value) {
            $result[] = $value->toArray();
        }

        return $result;
    }

    /**
     * Get iterator
     * @return \Traversable
     */
    function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->objects);
    }

    /**
     * Count number of objects in collection
     * @return int
     */
    function count(): int
    {
        return count($this->objects);
    }
}
