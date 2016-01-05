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
     * @method __construct
     *
     * @param string|null $name The connection name
     */
    public function __construct($name = null)
    {
        $name = $name ?: 'default';

        $config       = Config::get($name);
        $driver       = $this->drivers[$config['driver']];
        $this->driver = new $driver($config);

        if (!($this->driver instanceof Driver)) {
            throw new InvalidDriverException($driver.' is not a valid driver.');
        }

        static::$instances[$name] = $this;
    }

    /**
     * Return the default database instance.
     *
     * @method default_instance
     *
     * @return Instance
     */
    public static function default_instance()
    {
        if (isset(static::$instances['default'])) {
            return static::$instances['default'];
        }

        return static::$instances['default'] = new self();
    }

    /**
     * Compile and execute a query.
     *
     * @method execute
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
     * @method fetch
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
     * @method primaryKeyForTable
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
     * @method fieldsForTable
     *
     * @param Table $table The table
     *
     * @return string[] The field names for the table
     */
    public function fieldsForTable(Table $table)
    {
        return $this->driver->fieldsForTable($table);
    }
}
