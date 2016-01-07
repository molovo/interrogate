<?php

namespace Molovo\Interrogate\Database;

use Molovo\Interrogate\Collection;
use Molovo\Interrogate\Config;
use Molovo\Interrogate\Driver\Mysqli;
use Molovo\Interrogate\Driver\Pdo;
use Molovo\Interrogate\Exceptions\InvalidDriverException;
use Molovo\Interrogate\Interfaces\Driver;
use Molovo\Interrogate\Query;
use Molovo\Interrogate\Table;

class Instance
{
    /**
     * An array of cached table objects.
     *
     * @var Table[]
     */
    public static $tableCache = [];

    /**
     * The database name for this instance.
     *
     * @var string
     */
    public $databaseName = null;

    /**
     * An array of driver classes.
     *
     * @var array
     */
    private $drivers = [
        'mysqli'    => Mysqli::class,
        'pdo-mysql' => Pdo\Mysql::class,
        'pdo-pgsql' => Pdo\Pgsql::class,
    ];

    /**
     * The driver for this instance.
     *
     * @var Driver|null
     */
    private $driver = null;

    /**
     * All active instances.
     *
     * @var Instance[]
     */
    private static $instances = [];

    /**
     * Create a new instance of the database and driver.
     *
     * @param string|null $name The connection name
     */
    public function __construct($name = null)
    {
        // If a name isn't provided, then we'll use the default
        $this->name = $name ?: 'default';

        // Get the config for this instance
        $config = Config::get($this->name);

        // Store the database name
        $this->databaseName = $config['database'];

        // Initialise the driver
        $driverClass  = $this->drivers[$config['driver']];
        $this->driver = new $driverClass($config, $this);

        // If the driver isn't created, throw an exception
        if (!($this->driver instanceof Driver)) {
            throw new InvalidDriverException($driver.' is not a valid driver.');
        }

        // Cache the instance
        static::$instances[$this->name] = $this;
    }

    /**
     * Return the database instance.
     *
     * @return Instance
     */
    public static function instance($name)
    {
        if (isset(static::$instances[$name])) {
            return static::$instances[$name];
        }

        return static::$instances[$name] = new self($name);
    }

    /**
     * Return the default database instance.
     *
     * @return Instance
     */
    public static function default_instance()
    {
        return static::instance('default');
    }

    /**
     * Compile and execute a query.
     *
     * @param Query $query The query to execute
     *
     * @return bool
     */
    public function execute(Query $query)
    {
        if ($query->compiled === null) {
            $query->compile();
        }

        return $this->driver->execute($query);
    }

    /**
     * Compile and execute a query, and return its results.
     *
     * @param Query $query The query to execute
     *
     * @return Collection
     */
    public function fetch(Query $query)
    {
        if ($query->compiled === null) {
            $query->compile();
        }

        return $this->driver->fetch($query);
    }

    /**
     * Get the primary key field for a given table.
     *
     * @param Table $table The table
     *
     * @return string|null The primary key field name
     */
    public function primaryKeyForTable(Table $table)
    {
        return $this->driver->primaryKeyForTable($table);
    }

    /**
     * Get the fields for a given table.
     *
     * @param Table $table The table
     *
     * @return string[] The field names for the table
     */
    public function fieldsForTable(Table $table)
    {
        return $this->driver->fieldsForTable($table);
    }

    /**
     * Get the relationships for a given table.
     *
     * @param Table $table The table
     *
     * @return string[] The relationship names for the table
     */
    public function relationshipsForTable(Table $table)
    {
        return $this->driver->relationshipsForTable($table);
    }
}
