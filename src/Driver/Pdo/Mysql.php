<?php

namespace Molovo\Interrogate\Driver\Pdo;

use Molovo\Interrogate\Config;
use Molovo\Interrogate\Database\Instance;
use Molovo\Interrogate\Interfaces\Driver as DriverInterface;
use Molovo\Interrogate\Traits\PdoDriver;
use PDO;

class Mysql implements DriverInterface
{
    use PdoDriver;

    /**
     * Default configuration values for a mysqli connection.
     *
     * @var array
     */
    private $defaultConfig = [
        'username' => 'root',
        'hostname' => 'localhost',
        'port'     => 3306,
    ];

    /**
     * @inheritDoc
     */
    public function __construct(Config $config, Instance $instance)
    {
        $config = new Config(array_merge($this->defaultConfig, $config->toArray()));

        $this->instance = $instance;

        if (isset($config->socket)) {
            $dsn = 'mysql:'.'unix_socket='.$config->socket.';'
                         .'dbname='.$config->database;
        }

        if (!isset($config->socket)) {
            $dsn = 'mysql:'.'host='.$config->hostname.';'
                           .'port='.$config->port.';'
                           .'dbname='.$config->database;
        }

        $this->client = new PDO(
            $dsn,
            $config->username,
            $config->password
        );
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
        $result = $this->client->query("SHOW KEYS FROM $table->name WHERE Key_name = 'PRIMARY'");

        // If no results are returned, return null
        if ($result === false) {
            return;
        }

        // Fetch the query results
        $data = $result->fetch(PDO::FETCH_ASSOC);

        // Return the field name
        return $data['Column_name'];
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
        $result = $this->client->query("SHOW COLUMNS FROM $table->name");

        // If no results are returned, return an empty array
        if ($result === false) {
            return [];
        }

        // Loop through the query results, and add each field name to the array
        $fields = [];
        while ($data = $result->fetch(PDO::FETCH_ASSOC)) {
            $fields[] = $data['Field'];
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
                $relationships[Str::singularize($data['referenced_table_name'])] = (object) [
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
                $relationships[Str::pluralize($data['table_name'])] = (object) [
                    'column'     => $data['referenced_column_name'],
                    'table'      => $data['table_name'],
                    'references' => $data['column_name'],
                ];
            }
        }

        return $relationships;
    }
}
