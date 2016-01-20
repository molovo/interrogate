<?php

namespace Molovo\Interrogate\Driver\Pdo;

use Molovo\Interrogate\Config;
use Molovo\Interrogate\Database\Instance;
use Molovo\Interrogate\Interfaces\Driver as DriverInterface;
use Molovo\Interrogate\Table;
use Molovo\Interrogate\Traits\PdoDriver;
use Molovo\Str\Str;
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

        // Force foreign keys to be turned on
        $this->client->query('PRAGMA foreign_keys = ON;');
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
        while ($data = $result->fetch(PDO::FETCH_ASSOC)) {
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
        $result = $this->client->query("PRAGMA foreign_key_list(`$table->name`)");

        if ($result !== false) {
            // Loop through the relationships, and add each one to the array
            while ($data = $result->fetch(PDO::FETCH_ASSOC)) {
                $name                 = Str::singularize($data['table']);
                $relationships[$name] = (object) [
                    'column'     => $data['from'],
                    'table'      => $data['table'],
                    'references' => $data['to'],
                ];
            }
        }

        // Fetch all the to-many relationships
        $result = $this->client->query("SELECT sql
              FROM (
                    SELECT sql sql, type type, tbl_name tbl_name, name name
                      FROM sqlite_master
                     UNION ALL
                    SELECT sql, type, tbl_name, name
                      FROM sqlite_temp_master
                   )
             WHERE type != 'meta'
               AND sql NOTNULL
               AND name NOT LIKE 'sqlite_%'
             ORDER BY substr(type, 2, 1), name");

        // If no results are returned, return an empty array
        if ($result !== false) {
            // Loop through the relationships, and add each one to the array
            while ($data = $result->fetch(PDO::FETCH_ASSOC)) {
                preg_match_all("#((?:REFERENCES )(?P<table>$table->name)(?:\(.+\)))#", $data['sql'], $matches);

                if (isset($matches['table']) && $matches['table'][0] === $table->name) {
                    preg_match("#((?:CREATE TABLE )(?P<table>.+)(?: \())#", $data['sql'], $ref);
                    $ref_table = $ref['table'];

                    preg_match("#((?:FOREIGN KEY\()(?P<references>[\S]+)(?:\)))#", $data['sql'], $ref);
                    $references = $ref['references'];

                    preg_match("#((?:REFERENCES $table->name\()(?P<column>.+)(?:\)))#", $data['sql'], $ref);
                    $column = $ref['column'];

                    $name                 = Str::pluralize($ref_table);
                    $relationships[$name] = (object) [
                        'column'     => $column,
                        'table'      => $ref_table,
                        'references' => $references,
                    ];
                }
            }
        }

        return $relationships;
    }
}
