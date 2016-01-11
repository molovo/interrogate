<?php

namespace Molovo\Interrogate;

use Molovo\Interrogate\Database\Instance;

class Table
{
    /**
     * The table name.
     *
     * @var string|null
     */
    public $name = null;

    /**
     * An alias for this table to be used in queries.
     *
     * @var string|null
     */
    public $alias = null;

    /**
     * The database fields for this table.
     *
     * @var string[]
     */
    public $fields = [];

    /**
     * The instance this table belongs to.
     *
     * @var Instance|null
     */
    public $instance = null;

    /**
     * The primary key for this entity.
     *
     * @var string|null
     */
    public $primaryKey = null;

    /**
     * Retrieved a cached table, or create and store a new table object.
     *
     * @method find
     *
     * @param string   $name     The table name
     * @param string   $alias    An alias for the table
     * @param Instance $instance The instance the table belongs to
     *
     * @return self The table object
     */
    public static function find($name, $alias = null, Instance $instance = null)
    {
        if ($alias === null) {
            $alias = $name;
        }

        $instance = $instance ?: Database::instance('default');

        if (isset($instance::$tableCache[$name.'.'.$alias])) {
            return $instance::$tableCache[$name.'.'.$alias];
        }

        return new static($name, $alias, $instance);
    }

    /**
     * Create a new table object.
     *
     * @method __construct
     *
     * @param string        $name     The table name
     * @param string|null   $alias    An alias for use in queries
     * @param Instance|null $instance The instance the table belongs to
     */
    public function __construct($name, $alias = null, Instance $instance = null)
    {
        $this->name = $name;

        if ($alias === null) {
            $alias = $name;
        }
        $this->alias = $alias;

        $instance       = $instance ?: Database::instance('default');
        $this->instance = $instance;

        $this->primaryKey    = $this->instance->primaryKeyForTable($this);
        $this->fields        = $this->instance->fieldsForTable($this);
        $this->relationships = $this->instance->relationshipsForTable($this);

        $instance::$tableCache[$name.'.'.$alias] = $this;
    }

    public function hasField($fieldName)
    {
        if (empty($this->fields)) {
            $this->fields = $this->instance->fieldsForTable($this);
        }

        return in_array($fieldName, $this->fields);
    }
}
