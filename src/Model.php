<?php

namespace Molovo\Interrogate;

use Doctrine\Common\Inflector\Inflector;
use Molovo\Interrogate\Collection;
use Molovo\Interrogate\Database\Instance;
use Molovo\Interrogate\Query;
use Molovo\Interrogate\Table;
use ReflectionClass;

class Model
{
    /**
     * The table the model belongs to.
     *
     * @var Table|null
     */
    public $table = null;

    /**
     * The data attached to this model.
     *
     * @var array
     */
    private $data = [];

    /**
     * The data attached to this model which has been changed.
     *
     * @var array
     */
    private $originalData = [];

    /**
     * Whether the model exists in the database yet.
     *
     * @var bool
     */
    public $stored = false;

    /**
     * A table name, defined in custom models.
     *
     * @var string
     */
    protected static $tableName = null;

    /**
     * Create a new model.
     *
     * @method __construct
     *
     * @param Table         $table    The table the model belongs to
     * @param array         $data     The data to store against the model
     * @param Instance|null $instance The database instance the
     *                                model belongs to
     */
    public function __construct(Table $table, array $data = [])
    {
        $this->table = $table;
        $this->data  = $data;
    }

    /**
     * Get the table for the current model.
     *
     * @method getTable
     *
     * @return Table|null
     */
    public static function getTable()
    {
        $class = implode('', array_slice(explode('\\', static::class), -1));

        if (static::$tableName === null) {
            static::$tableName = Inflector::pluralize(Inflector::tableize($class));
        }

        $alias = Inflector::pluralize(Inflector::tableize($class));

        return Table::find(static::$tableName, $alias);
    }

    /**
     * When inaccessible methods are called statically, create a Query object
     * for the model, and check the query for a non-static method of the same
     * name. If the method does not exist within the Query class, execute the
     * query, and try the same method on the resulting Collection. If neither
     * exist, just return null.
     *
     * @method __callStatic
     *
     * @param string $methodName The method being called
     * @param array  $args       Arguments to the method
     *
     * @return mixed
     */
    public static function __callStatic($methodName, array $args)
    {
        // First, create a query against the model's table
        $table = static::getTable();
        $query = Query::table($table);

        // Create a reference class against Query
        $queryRef    = new \ReflectionClass(Query::class);

        // Check if the method exists on the Query class
        if ($queryRef->hasMethod($methodName)) {
            // Invoke the method with the provided arguments
            $method = $queryRef->getMethod($methodName);

            // Return the results
            return $method->invokeArgs($query, $args);
        }

        // If we get here, the method does not exist within the Query class,
        // so we fetch the results of the query, and try the same method on the
        // collection class
        $collection    = $query->fetch();

        // Create a reference class against Collection
        $collectionRef = new \ReflectionClass(Collection::class);

        // Check if the method exists on the Collection class
        if ($collectionRef->hasMethod($methodName)) {
            // Invoke the method with the provided arguments
            $method = $collectionRef->getMethod($methodName);

            // Return the results
            return $method->invokeArgs($collection, $args);
        }

        return;
    }

    /**
     * Retrieve model properties from the data array.
     *
     * @method __get
     *
     * @param string $key The property to retrieve
     *
     * @return mixed The value
     */
    public function __get($key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return;
    }

    /**
     * Set model properties within the changed data array,
     * so that they can be saved later.
     *
     * @method __set
     *
     * @param string $key   The property to set
     * @param mixed  $value The value to set
     *
     * @return mixed The new value
     */
    public function __set($key, $value)
    {
        // Check that the field being updated is an actual database field
        if ($this->table->hasField($key)) {
            // Remember the old value
            $this->originalData[$key] = $this->data[$key];
        }

        return $this->data[$key] = $value;
    }

    /**
     * Revert a changed property to its original value. If no
     * key is passed, all properties are reverted.
     *
     * @method revert
     *
     * @param string $key The key to revert
     *
     * @return mixed The new value
     */
    public function revert($key = null)
    {
        if ($key === null) {
            return $this->revertAll();
        }

        $this->data[$key] = $this->originalData[$key];

        return $this->data[$key];
    }

    /**
     * Revert all changed properties to their original values.
     *
     * @method revertAll
     *
     * @return array The new values
     */
    public function revertAll()
    {
        return $this->data = $this->originalData;
    }

    /**
     * Save the model's changed data to the database.
     *
     * @method save
     *
     * @return bool Success/Failure
     */
    public function save()
    {
        if (empty($this->originalData)) {
            return true;
        }

        $changed = array_keys($this->originalData);
        $primary = $this->table->primaryKey;

        $data = [];
        foreach ($changed as $key) {
            $data[$key] = $this->data[$key];
        }

        $this->updateTimestamp();

        $update = Query::table($this->table)
            ->where($primary, $this->{$primary})
            ->update($data);

        if ($update) {
            $this->originalData = [];
        }

        return $update;
    }

    /**
     * Save the model's changed data at the end of the request.
     *
     * @method saveLater
     */
    public function saveLater()
    {
        $model = $this;
        register_shutdown_function(function () use ($model) {
            $model->save();
        });
    }

    /**
     * Create a duplicate of an model, ready to be saved to the database.
     *
     * @method clone
     *
     * @return self The cloned model
     */
    public function duplicate()
    {
        $clone                             = clone $this;
        $clone->stored                     = false;
        $clone->{$this->table->primaryKey} = null;

        return $clone;
    }

    /**
     * Update the models updated_at column.
     *
     * @method updateTimestamp
     */
    public function updateTimestamp()
    {
        $this->updated_at = date('Y-m-d H:i:s');
    }

    /**
     * Update the models updated_at column and save it.
     *
     * @method touch
     */
    public function touch()
    {
        $this->updateTimestamp();
        $this->save();
    }

    /**
     * Use the model's ID when converting to string.
     *
     * @method __toString
     *
     * @return string The model's ID
     */
    public function __toString()
    {
        return $this->{$this->table->primaryKey};
    }

    /**
     * Recursively format the contents of the model as an array.
     *
     * @method toArray
     *
     * @return array
     */
    public function toArray()
    {
        $output = [];

        foreach ($this->data as $key => $value) {
            // Convert any attached models or collections recursively
            if ($value instanceof self || $value instanceof Collection) {
                $value = $value->toArray();
            }
            $output[$key] = $value;
        }

        return $output;
    }

    /**
     * Recursively format the contents of the model as JSON.
     *
     * @method toJson
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }
}
