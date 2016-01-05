<?php

namespace Molovo\Interrogate;

class Collection implements \IteratorAggregate
{
    private $_objects = [];

    /**
     * Create a new Collection.
     *
     * @method __construct
     *
     * @param Model[] $objects An array of objects
     */
    public function __construct(array $objects = [])
    {
        foreach ($objects as $object) {
            $key                  = $object->{$object->table->primaryKey};
            $this->_objects[$key] = $object;
        }
    }

    /**
     * Use the object IDs when converting to string.
     *
     * @method __toString
     *
     * @return string The object IDs
     */
    public function __toString()
    {
        return implode(', ', array_keys($this->_objects));

        return $this->{$this->table->primaryKey};
    }

    /**
     * Attach a new object to the collection.
     *
     * @method attach
     *
     * @param Model $object The object to attach
     *
     * @return $this
     */
    public function attach(Model $object)
    {
        $key                  = $object->{$object->table->primaryKey};
        $this->_objects[$key] = $object;

        return $this;
    }

    /**
     * Detach an existing object from the collection.
     *
     * @method detach
     *
     * @param Model $object The object to detach
     *
     * @return $this
     */
    public function detach(Model $object)
    {
        $key = $object->{$object->table->primaryKey};
        if (isset($this->_objects[$key])) {
            unset($this->_objects[$key]);
        }

        return $this;
    }

    /**
     * Retrieve the first object in the collection.
     *
     * @method first
     *
     * @return Model
     */
    public function first()
    {
        $objects = $this->_objects;

        return array_shift($objects);
    }

    /**
     * Retrieve the last object in the collection.
     *
     * @method last
     *
     * @return Model
     */
    public function last()
    {
        $objects = $this->_objects;

        return array_pop($objects);
    }

    /**
     * Retrieve a single object from the set by its ID.
     *
     * @method find
     *
     * @param int $id The object's primary key
     *
     * @return Model
     */
    public function find($id)
    {
        if (isset($this->_objects[$id])) {
            return $this->_objects[$id];
        }

        return;
    }

    /**
     * Create the ArrayIterator that allows us to call foreach on the object.
     *
     * @method getIterator
     *
     * @return \ArrayIterator The iterator object
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->_objects);
    }

    /**
     * Recursively format the contents of the collection as an array.
     *
     * @method toArray
     *
     * @return array
     */
    public function toArray()
    {
        $output = [];

        foreach ($this->_objects as $object) {
            $output[] = $object->toArray();
        }

        return $output;
    }

    /**
     * Recursively format the contents of the collection as a JSON string.
     *
     * @method toJson
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Format the contents of the collection as a key => value array suitable
     * for a dropdown list.
     *
     * @method toList
     *
     * @param string $key   The key to list by
     * @param string $value The value for the list
     *
     * @return array
     */
    public function toList($value = 'name', $key = null)
    {
        $output = [];

        foreach ($this->_objects as $object) {
            if ($key === null) {
                $key = $object->table->primaryKey;
            }
            $output[$object->{$key}] = $object->{$value};
        }

        return $output;
    }
}
