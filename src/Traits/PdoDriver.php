<?php

namespace Molovo\Interrogate\Traits;

use Molovo\Interrogate\Collection;
use Molovo\Interrogate\Config;
use Molovo\Interrogate\Database\Instance;
use Molovo\Interrogate\Exceptions\QueryExecutionException;
use Molovo\Interrogate\Query;
use PDO;
use PDOStatement;

trait PdoDriver
{
    use Driver;

    /**
     * @inheritDoc
     */
    public function __construct(Config $config, Instance $instance)
    {
    }

    /**
     * Execute a query.
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

        return $this->error($stmt);
    }

    /**
     * Execute a query, and return the results as models.
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

        return $this->error($stmt);
    }

    /**
     * Throw an exception, as query execution failed.
     *
     * @throws QueryExecutionException
     */
    private function error(PDOStatement $stmt)
    {
        // Throw an exception, as the query failed
        $error = $stmt->errorInfo();
        throw new QueryExecutionException('PDO error '.$error[1].': '.$error[2]);
    }

    /**
     * Prepare a query for execution.
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
            return $this->error($stmt);
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
