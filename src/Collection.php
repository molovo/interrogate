<?php

namespace Molovo\Interrogate;

class Collection implements \IteratorAggregate
{
    /**
     * The array of models in the Collection.
     *
     * @var Model[]
     */
    private $models = [];

    /**
     * Create a new Collection.
     *
     * @method __construct
     *
     * @param Model[] $models An array of models
     */
    public function __construct(array $models = [])
    {
        foreach ($models as $model) {
            $key                  = $model->{$model->table->primaryKey};
            $this->models[$key]   = $model;
        }
    }

    /**
     * Use the model IDs when converting to string.
     *
     * @method __toString
     *
     * @return string The model IDs
     */
    public function __toString()
    {
        return implode(', ', array_keys($this->models));

        return $this->{$this->table->primaryKey};
    }

    /**
     * Attach a new model to the collection.
     *
     * @method attach
     *
     * @param Model $model The model to attach
     *
     * @return $this
     */
    public function attach(Model $model)
    {
        $key                  = $model->{$model->table->primaryKey};
        $this->models[$key]   = $model;

        return $this;
    }

    /**
     * Detach an existing model from the collection.
     *
     * @method detach
     *
     * @param mixed $id The ID of the model to detach
     *
     * @return $this
     */
    public function detach($id)
    {
        if (isset($this->models[$id])) {
            unset($this->models[$id]);
        }

        return $this;
    }

    /**
     * Retrieve the first model in the collection.
     *
     * @method first
     *
     * @return Model
     */
    public function first()
    {
        $models = $this->models;

        return array_shift($models);
    }

    /**
     * Retrieve the last model in the collection.
     *
     * @method last
     *
     * @return Model
     */
    public function last()
    {
        $models = $this->models;

        return array_pop($models);
    }

    /**
     * Retrieve a single model from the set by its ID.
     *
     * @method find
     *
     * @param int $id The model's primary key
     *
     * @return Model
     */
    public function find($id)
    {
        if (isset($this->models[$id])) {
            return $this->models[$id];
        }

        return;
    }

    /**
     * Create the ArrayIterator that allows us to call foreach on the model.
     *
     * @method getIterator
     *
     * @return \ArrayIterator The iterator model
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->models);
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

        foreach ($this->models as $model) {
            $output[] = $model->toArray();
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

        foreach ($this->models as $model) {
            if ($key === null) {
                $key = $model->table->primaryKey;
            }
            $output[$model->{$key}] = $model->{$value};
        }

        return $output;
    }
}
