<?php

namespace Molovo\Interrogate;

use Molovo\Interrogate\Database\Instance;
use Molovo\Str\Str;

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
     * The id column for the model.
     *
     * @var string
     */
    protected static $primaryKeyColumn = null;

    /**
     * Create a new model.
     *
     * @param Table         $table    The table the model belongs to
     * @param array         $data     The data to store against the model
     * @param Instance|null $instance The database instance the
     *                                model belongs to
     */
    public function __construct(Table $table = null, array $data = [])
    {
        if ($table === null) {
            $table = new Table(static::$tableName);
        }

        $this->table = $table;
        $this->data  = $data;
    }

    /**
     * Get the table for the current model.
     *
     * @return Table|null
     */
    public static function getTable()
    {
        $class = implode('', array_slice(explode('\\', static::class), -1));

        if (static::$tableName === null) {
            static::$tableName = Str::pluralize(Str::snakeCase($class));
        }

        $alias = Str::pluralize(Str::snakeCase($class));

        return Table::find(static::$tableName, $alias);
    }

    /**
     * When inaccessible methods are called statically, create a Query object
     * for the model, and check the query for a non-static method of the same
     * name. If the method does not exist within the Query class, execute the
     * query, and try the same method on the resulting Collection. If neither
     * exist, just return null.
     *
     * @param string $methodName The method being called
     * @param array  $args       Arguments to the method
     *
     * @return mixed
     */
    public static function __callStatic($methodName, array $args)
    {
        if ($query = static::getQueryRef($methodName, $args)) {
            return $query;
        }

        if ($collection = static::getCollectionRef($query, $methodName, $args)) {
            return $collection;
        }

        return;
    }

    /**
     * Allows for creating queries against a model's relationships by calling
     * the relationship name as a method.
     *
     * @param string $methodName The method being called
     * @param array  $args       Arguments to the method
     *
     * @return mixed
     */
    public function __call($methodName, array $args)
    {
        if (isset($this->table->relationships[$methodName])) {
            $relationship = $this->table->relationships[$methodName];

            return Query::table($relationship->table)
                ->setModel(static::class)
                ->where($relationship->references, $this->{$relationship->column});
        }
    }

    /**
     * Retrieve model properties from the data array.
     *
     * @param string $key The property to retrieve
     *
     * @return mixed The value
     */
    public function __get($key)
    {
        if (isset($this->table->relationships[$key])) {
            return $this->{$key}()->fetch();
        }
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return;
    }

    /**
     * Set model properties within the changed data array,
     * so that they can be saved later.
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
     * Get the primary key column for the model.
     *
     * @return [type] [description]
     */
    public static function primaryKeyColumn()
    {
        if (static::$primaryKeyColumn !== null) {
            return static::$primaryKeyColumn;
        }

        if (($table = new Table(static::$tableName)) && ($primary = $table->primaryKey) !== null) {
            return $primary;
        }

        return;
    }

    public function primaryKey()
    {
        return $this->{static::primaryKeyColumn()};
    }

    /**
     * Get a query ref for a statically called method.
     *
     * @param string $methodName The method being called
     * @param array  $args       Arguments to the method
     *
     * @return mixed
     */
    private static function getQueryRef($methodName, $args)
    {
        // First, create a query against the model's table
        $table = static::getTable();

        $query = Query::table($table);
        $query->setModel(static::class);

        // Create a reference class against Query
        $queryRef    = new \ReflectionClass(Query::class);

        // Check if the method exists on the Query class
        if ($queryRef->hasMethod($methodName)) {
            // Invoke the method with the provided arguments
            $method = $queryRef->getMethod($methodName);

            // Return the results
            return $method->invokeArgs($query, $args);
        }

        return $query;
    }

    /**
     * Get a collection ref for a statically called method.
     *
     * @param Query  $query      The base query to return a collection
     * @param string $methodName The method being called
     * @param array  $args       Arguments to the method
     *
     * @return mixed
     */
    private static function getCollectionRef(Query $query, $methodName, array $args)
    {
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

        return $collection;
    }

    /**
     * Revert a changed property to its original value. If no
     * key is passed, all properties are reverted.
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
     * @return array The new values
     */
    public function revertAll()
    {
        return $this->data = $this->originalData;
    }

    /**
     * Save the model's changed data to the database.
     *
     * @return bool Success/Failure
     */
    public function save()
    {
        if (!$this->stored) {
            return $this->create();
        }

        if (empty($this->originalData)) {
            return true;
        }

        return $this->update();
    }

    /**
     * Save the model's changed data at the end of the request.
     */
    public function saveLater()
    {
        $model = $this;
        register_shutdown_function(function () use ($model) {
            $model->save();
        });
    }

    /**
     * Update the model in the database.
     *
     * @return bool
     */
    public function update()
    {
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
     * Create the model in the database.
     *
     * @return bool
     */
    public function create()
    {
        $this->updateTimestamps();

        $insert = Query::table($this->table)
            ->insert($this->data);

        if ($insert) {
            $this->stored = true;
        }

        return $insert;
    }

    /**
     * Delete the model.
     *
     * @return bool
     */
    public function delete()
    {
        if (!$this->stored) {
            return;
        }

        $query = Query::table($this->table->name, $this->table->name)
            ->where($this->table->primaryKey, $this->{$this->table->primaryKey});

        return $query->delete();
    }

    /**
     * Create a duplicate of an model, ready to be saved to the database.
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
     * Update the model's updated_at column.
     */
    public function updateTimestamp()
    {
        $this->updated_at = date('Y-m-d H:i:s');
    }

    /**
     * Update both the model's timestamp columns.
     */
    public function updateTimestamps()
    {
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
    }

    /**
     * Update the models updated_at column and save it.
     */
    public function touch()
    {
        $this->updateTimestamp();
        $this->save();
    }

    /**
     * Use the model's ID when converting to string.
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
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }
}
