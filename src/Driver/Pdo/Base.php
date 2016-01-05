<?php

namespace Molovo\Interrogate\Driver\Pdo;

use Molovo\Interrogate\Collection;
use Molovo\Interrogate\Exceptions\QueryExecutionException;
use Molovo\Interrogate\Interfaces\Driver as DriverInterface;
use Molovo\Interrogate\Query;
use Molovo\Interrogate\Table;
use Molovo\Interrogate\Traits\Driver;
use PDO;
use PDOStatement;

class Base implements DriverInterface
{
    use Driver;

    /**
     * @inheritDoc
     */
    public function __construct(array $config = [])
    {
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

        return $stmt->execute();
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
        if ($stmt->execute()) {
            // Package the results into models
            return $this->packageResults($stmt, $query);
        }

        // Throw an exception, as the query failed
        $error = $stmt->errorInfo();
        throw new QueryExecutionException('PDO error '.$error[1].': '.$error[2]);
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

        $data = $result->fetch(PDO::FETCH_ASSOC);

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
        $data   = $result->fetch(PDO::FETCH_ASSOC);

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
        while ($data = $result->fetch(PDO::FETCH_ASSOC)) {
            $fields[] = $data['Field'];
        }

        return $fields;
    }

    /**
     * Prepare a query for execution.
     *
     * @method prepareQuery
     *
     * @param Query $query The query to prepare
     *
     * @return PDOStatement The prepared statement
     */
    private function prepareQuery(Query $query)
    {
        // Pass the compiled query to mysqli_prepare
        $stmt = $this->client->prepare($query->compiled);

        // If mysqli_prepare returns false, or the returned value is not
        // a mysqli_stmt, then an error has occurred
        if ($stmt === false || !($stmt instanceof PDOStatement)) {
            $error = $stmt->errorInfo();
            throw new QueryExecutionException('PDO error '.$error[1].': '.$error[2]);
        }

        // We only need to bind parameters if vars exist
        if (sizeof($query->vars) > 0) {
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
     * @param PDOStatement $stmt The statement
     * @param array        $vars The variables to bind
     */
    private function bindParams(PDOStatement &$stmt, array $vars = [])
    {
        // Loop through each of the query vars, and calculate their types
        foreach ($vars as $pos => $var) {
            switch (true) {
                case is_bool($var):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($var):
                    $type = PDO::PARAM_NULL;
                    break;
                case is_int($var):
                    $type = PDO::PARAM_INT;
                    break;
                default:
                    $type = PDO::PARAM_STR;
                    break;
            }

            $stmt->bindValue(++$pos, $var, $type);
        }
    }

    /**
     * Package query results into models.
     *
     * @method packageResults
     *
     * @param PDOStatement $result The statement containing results to package
     * @param Query        $query  The query which produced the results
     *
     * @return Collection The results
     */
    private function packageResults(PDOStatement $result, $query)
    {
        // Create an empty collection to store results in
        $collection     = new Collection;

        $collection->totalRows = $this->foundRows();

        // Loop through the query results
        while (($data = $result->fetch(PDO::FETCH_ASSOC))) {
            // Prepare the row data for storage within a model
            $prepared = $this->prepareData($data);

            // Package the row into a model
            $this->packageModel($prepared, $collection, $query);
        }

        return $collection;
    }
}
