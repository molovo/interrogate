<?php

namespace Molovo\Interrogate\Driver\Pdo;

use Molovo\Interrogate\Config;
use Molovo\Interrogate\Database\Instance;
use Molovo\Interrogate\Interfaces\Driver as DriverInterface;
use Molovo\Interrogate\Table;
use Molovo\Interrogate\Traits\PdoDriver;
use PDO;

class Sqlite implements DriverInterface
{
    use PdoDriver;

    /**
     * Since the only required config value is the DB path, and we
     * can't guess that, there is no default config.
     *
     * @var [type]
     */
    private $defaultConfig = [];

    /**
     * @inheritDoc
     */
    public function __construct(Config $config, Instance $instance)
    {
        $this->instance = $instance;

        $dsn = 'sqlite:'.$config->database;

        $this->client = new PDO($dsn);
    }

    /**
     * Return the number of found rows for a query.
     *
     * @return int
     */
    public function foundRows()
    {
        // Execute the query directly
        $result = $this->client->query('SELECT FOUND_ROWS() AS "count"');

        // If no results are returned, return an empty array
        if ($result === false) {
            return [];
        }

        $data = $result->fetch(PDO::FETCH_ASSOC);

        return (int) $data['count'];
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
        // Execute the query directly
        $result = $this->client->query("PRAGMA table_info(`$table->name`)");

        // If no results are returned, return null
        if ($result === false) {
            return;
        }

        // Fetch the query results
        while ($data   = $result->fetch(PDO::FETCH_ASSOC)) {
            if ((int) $data['pk'] === 1) {
                // Return the field name
                return $data['name'];
            }
        }

        return;
    }

    /**
     * Get the field names for a given table.
     *
     * @param Table $table The table
     *
     * @return string[] An array of field names
     */
    public function fieldsForTable(Table $table)
    {
        // Execute the query directly
        $result = $this->client->query("PRAGMA table_info(`$table->name`);");

        // If no results are returned, return an empty array
        if ($result === false) {
            return [];
        }

        // Loop through the query results, and add each field name to the array
        $fields = [];
        while ($data = $result->fetch(PDO::FETCH_ASSOC)) {
            $fields[] = $data['name'];
        }

        return $fields;
    }

    /**
     * Get the field names for a given table.
     *
     * @param Table $table The table
     *
     * @return string[] An array of field names
     */
    public function relationshipsForTable(Table $table)
    {
        $database      = $this->instance->databaseName;
        $relationships = [];

        // Fetch all the to-one relationships
        $result = $this->client->query("SELECT `constraint_name`, `column_name`, `referenced_table_name`, `referenced_column_name` FROM `information_schema`.`key_column_usage` WHERE `referenced_table_name` IS NOT NULL AND `table_schema`='$database' AND `table_name`='$table->name'");

        if ($result !== false) {
            // Loop through the relationships, and add each one to the array
            while ($data = $result->fetch(PDO::FETCH_ASSOC)) {
                $name                 = Str::singularize($data['constraint_name']);
                $relationships[$name] = (object) [
                    'column'     => $data['column_name'],
                    'table'      => $data['referenced_table_name'],
                    'references' => $data['referenced_column_name'],
                ];
            }
        }

        // Fetch all the to-many relationships
        $result = $this->client->query("SELECT `constraint_name`, `column_name`, `table_name`, `referenced_column_name` FROM `information_schema`.`key_column_usage` WHERE `table_name` IS NOT NULL AND `table_schema`='$database' AND `referenced_table_name`='$table->name'");

        // If no results are returned, return an empty array
        if ($result !== false) {
            // Loop through the relationships, and add each one to the array
            while ($data = $result->fetch(PDO::FETCH_ASSOC)) {
                $name                 = Str::pluralize($data['constraint_name']);
                $relationships[$name] = (object) [
                    'column'     => $data['referenced_column_name'],
                    'table'      => $data['table_name'],
                    'references' => $data['column_name'],
                ];
            }
        }

        return $relationships;
    }
}
