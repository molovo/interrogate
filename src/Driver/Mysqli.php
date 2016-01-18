<?php

namespace Molovo\Interrogate\Driver;

use Molovo\Interrogate\Collection;
use Molovo\Interrogate\Config;
use Molovo\Interrogate\Database\Instance;
use Molovo\Interrogate\Exceptions\QueryExecutionException;
use Molovo\Interrogate\Interfaces\Driver as DriverInterface;
use Molovo\Interrogate\Model;
use Molovo\Interrogate\Query;
use Molovo\Interrogate\Table;
use Molovo\Interrogate\Traits\Driver;
use Molovo\Str\Str;
use mysqli as Client;
use mysqli_result as Result;
use mysqli_stmt as Statement;
use ReflectionClass;

class Mysqli implements DriverInterface
{
    use Driver;

    /**
     * Default configuration values for a mysqli connection.
     *
     * @var array
     */
    private $defaultConfig = [
        'username' => 'root',
        'hostname' => 'localhost',
        'port'     => 3306,
        'socket'   => '/tmp/mysql.sock',
    ];

    /**
     * @inheritDoc
     */
    public function __construct(Config $config, Instance $instance)
    {
        $config       = new Config(array_merge($this->defaultConfig, $config->toArray()));
        $this->client = new Client(
            $config->hostname,
            $config->username,
            $config->password,
            $config->database,
            $config->port,
            $config->socket
        );

        $this->instance = $instance;
    }

    /**
     * Execute a query.
     *
     * @method execute
     *
     * @param Query $query The query to Execute
     *
     * @return bool The success of the execution
     */
    public function execute(Query $query)
    {
        if ($query->compiled === null) {
            $query->compile();
        }

        $stmt = $this->prepareQuery($query);

        if ($success = $stmt->execute()) {
            return $success;
        }

        return $this->error();
    }

    /**
     * Execute a query, and return the results as models.
     *
     * @method fetch
     *
     * @param Query $query The query to execute
     *
     * @throws QueryExecutionException
     *
     * @return Collection A set containing results
     */
    public function fetch(Query $query)
    {
        if ($query->compiled === null) {
            $query->compile();
        }

        // Prepare the statement
        $stmt = $this->prepareQuery($query);

        // Execute the statement
        $success = $stmt->execute();

        // Get the result object
        $result  = $stmt->get_result();

        if ($success && $result) {
            // Package the results into models
            return $this->packageResults($result, $query);
        }

        return $this->error();
    }

    /**
     * Throw an exception, as query execution failed.
     *
     * @method error
     *
     * @throws QueryExecutionException
     */
    private function error()
    {
        // Throw an exception, as the query failed
        throw new QueryExecutionException('Mysqli error '.$this->client->errno.': '.$this->client->error);
    }

    /**
     * Return the number of found rows for a query.
     *
     * @method foundRows
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

        $data = $result->fetch_assoc();

        return (int) $data['count'];
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
        // Execute the query directly
        $result = $this->client->query("SHOW KEYS FROM $table->name WHERE Key_name = 'PRIMARY'");

        // If no results are returned, return null
        if ($result === false) {
            return;
        }

        // Fetch the query results
        $data   = $result->fetch_assoc();

        // Return the field name
        return $data['Column_name'];
    }

    /**
     * Get the field names for a given table.
     *
     * @method primaryKeyForTable
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
        while ($data = $result->fetch_assoc()) {
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
            while ($data = $result->fetch_assoc()) {
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
            while ($data = $result->fetch_assoc()) {
                $relationships[Str::pluralize($data['table_name'])] = (object) [
                    'column'        => $data['referenced_column_name'],
                    'table'         => $data['table_name'],
                    'references'    => $data['column_name'],
                ];
            }
        }

        return $relationships;
    }

    /**
     * Prepare a query for execution.
     *
     * @method prepareQuery
     *
     * @param Query $query The query to prepare
     *
     * @return Statement The prepared statement
     */
    private function prepareQuery(Query $query)
    {
        // Pass the compiled query to mysqli_prepare
        $stmt = $this->client->prepare($query->compiled);

        // If mysqli_prepare returns false, or the returned value is not
        // a mysqli_stmt, then an error has occurred
        if ($stmt === false || !($stmt instanceof Statement)) {
            throw new QueryExecutionException('Mysqli error '.$this->client->errno.': '.$this->client->error);
        }

        // We only need to bind parameters if vars exist
        if (count($query->vars) > 0) {
            $this->bindParams($stmt, $query->vars);
        }

        // Return the prepared statement
        return $stmt;
    }

    /**
     * Bind the query variables to a prepared statement.
     *
     * @method bindParams
     *
     * @param Statement $stmt The statement
     * @param array     $vars The variables to bind
     */
    private function bindParams(Statement &$stmt, array $vars = [])
    {
        $types  = '';
        $params = [];

        // Loop through each of the query vars, and calculate their types
        foreach ($vars as $var) {
            $type = 's';

            if (is_float($var)) {
                $type = 'd';
            }

            if (is_int($var)) {
                $type = 'i';
            }

            $types .= $type;
        }

        // Add the types string to the beginning of the array
        $params = [$types];

        // Add each of the vars to the array
        foreach ($vars as $key => $var) {
            $params[] = &$vars[$key];
        }

        // Pass the array as parameters to mysqli_stmt_bind_param
        $ref  = new ReflectionClass('mysqli_stmt');
        $bind = $ref->getMethod('bind_param');
        $bind->invokeArgs($stmt, $params);
    }

    /**
     * Package query results into models.
     *
     * @method packageResults
     *
     * @param Result $result The results to package
     * @param Query  $query  The query which produced the results
     *
     * @return Collection The results
     */
    private function packageResults(Result $result, $query)
    {
        // Create an empty collection to store results in
        $collection     = new Collection;

        $collection->totalRows = $this->foundRows();

        // Loop through the query results
        while (($data = $result->fetch_assoc()) !== null) {
            // Prepare the row data for storage within a model
            $prepared = $this->prepareData($data);

            // Package the row into a model
            $this->packageModel($prepared, $collection, $query);
        }

        return $collection;
    }
}
