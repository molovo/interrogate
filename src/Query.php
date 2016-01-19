<?php

namespace Molovo\Interrogate;

use Molovo\Interrogate\Database\Instance;
use Molovo\Interrogate\Exceptions\QuerySyntaxError;
use Molovo\Interrogate\Query\Builder;
use Molovo\Str\Str;

class Query
{
    /**
     * Query Types.
     */
    const SELECT       = 'select';
    const SELECT_COUNT = 'select_count';
    const UPDATE       = 'update';
    const INSERT       = 'insert';
    const DELETE       = 'delete';

    /**
     * Join Types.
     */
    const LEFT_JOIN  = 'left_join';
    const RIGHT_JOIN = 'right_join';
    const INNER_JOIN = 'inner_join';

    /**
     * The table to query.
     *
     * @var Table|null
     */
    public $table = null;

    /**
     * The model class to use for returned objects.
     *
     * @var string
     */
    public $model = null;

    /**
     * The fields to select.
     *
     * @var array
     */
    public $fields = [];

    /**
     * The total number of rows that match the
     * current query, regardless of limit.
     *
     * @var int|null
     */
    public $totalRows = null;

    /**
     * The where clauses to be applied to the query.
     *
     * @var array
     */
    public $clauses = [];

    /**
     * The on clauses to be applied to the query if it is a join.
     *
     * @var array
     */
    public $onClauses = [];

    /**
     * The having clauses to be applied to the query.
     *
     * @var array
     */
    public $havingClauses = [];

    /**
     * Joins to other tables for this query.
     *
     * @var Query[]
     */
    public $joins = [];

    /**
     * The parent query.
     *
     * @var Query|null
     */
    public $parent = null;

    /**
     * Fields by which the results will be sorted.
     *
     * @var array
     */
    public $updateFields = [];

    /**
     * Fields by which the results will be sorted.
     *
     * @var array
     */
    public $orderFields = [];

    /**
     * Fields by which the results will be grouped.
     *
     * @var array
     */
    public $groupFields = [];

    /**
     * The limit to apply to the query.
     *
     * @var int|null
     */
    public $limit = null;

    /**
     * The offset to apply to the query.
     *
     * @var int|null
     */
    public $offset = null;

    /**
     * The compiled query string.
     *
     * @var string|null
     */
    public $compiled = null;

    /**
     * The type of the query.
     *
     * @var string
     */
    public $type = self::SELECT;

    /**
     * The variables to use as bind parameters for the query.
     *
     * @var array
     */
    public $vars = [];

    /**
     * The instance to use when executing the query.
     *
     * @var Instance|null
     */
    private $instance = null;

    /**
     * Create a new query.
     *
     * @param Table|string  $table    The table to query
     * @param string|null   $alias    An alias to use
     * @param Instance|null $instance The instance to use
     */
    public function __construct($table, $alias = null, Instance $instance = null)
    {
        // If a table name is passed, rather than an instance of Table,
        // then create the table object here
        if (!($table instanceof Table)) {
            $table = Table::find($table, $alias);
        }

        $this->table        = $table;
        $this->instance     = $instance ?: Database::instance('default');
    }

    /**
     * Magic method which allows for dynamically adding where clauses by
     * including the camelCased column name in the method name.
     *
     * e.g. `$query->whereColumnName(3)` is equivalent to `$query->where('column_name', 3)`
     *
     * @param string $methodName The method being called
     * @param array  $args       The arguments provided to the method
     *
     * @return $this
     */
    public function __call($methodName, $args)
    {
        // Convert the method name to snake_case
        $methodName = Str::snakeCase($methodName);

        // Explode the method name, and separate the first word
        $words     = explode('_', $methodName);
        $firstWord = array_shift($words);

        // If the method name begins with 'where', convert the rest of the
        // method name into a column name, and add a where clause to the query
        if ($firstWord === 'where') {
            // Get the column name from the method name
            $columnName = implode('_', $words);

            // Add the column name to the beginning of the args array
            array_unshift($args, $columnName);

            // Create new reflection on the Query class
            $ref = new \ReflectionClass(self::class);

            // Invoke the `where` method with the provided arguments
            $method = $ref->getMethod('where');
            $method->invokeArgs($this, $args);
        }

        // Return the query for chaining
        return $this;
    }

    public function setModel($model)
    {
        $this->model = $model;
    }

    /**
     * Statically create a new Query.
     *
     * @param Table|string  $table    The table name to query
     * @param string|null   $alias    An alias for the Query
     * @param Instance|null $instance The instance to use
     *
     * @return self
     */
    public static function table($table, $alias = null, Instance $instance = null)
    {
        return new self($table, $alias, $instance);
    }

    /**
     * Set the fields to be selected.
     *
     * Accepts strings or Query objects as parameters.
     *
     * @return $this
     */
    public function select()
    {
        $args = func_get_args();

        // If nothing is passed, then select all
        if (count($args) === 0) {
            $args = ['*'];
        }

        // If null is passed, then do not select any fields
        if (count($args) === 1 && $args[0] === null) {
            $args = [];
        }

        // If a query is passed, set its parent parameter
        foreach ($args as &$arg) {
            if ($arg instanceof self) {
                $arg->parent = $this;
            }
        }

        // If the primary key is not included, add it as the first field
        if (!in_array($this->table->primaryKey, $args)) {
            array_unshift($args, $this->table->primaryKey);
        }

        $this->fields[$this->table->alias] = $args;

        return $this;
    }

    /**
     * Add a where clause to the query.
     *
     * @param string      $column    The column to compare.
     * @param string|null $operator  The operator to use for the clause.
     *                               A default of '=' is assumed, and if that
     *                               is the case, the value for comparison can
     *                               be placed here as a shorthand.
     * @param mixed       $value     The value to compare.
     * @param string      $delimiter The delimiter used for imploding clauses.
     *
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $delimiter = 'AND')
    {
        // If only two arguments are passed, add an '=' as the operator
        if (func_num_args() === 2 && $operator !== null) {
            $value = $operator;

            return $this->where($column, '=', $value);
        }

        // If this is the first where clause, force the WHERE keyword
        if (count($this->clauses) === 0) {
            $delimiter = 'WHERE';
        }

        // If the value is a subquery, set its parent parameter
        if ($value instanceof self) {
            $value->parent = $this;
        }

        // Store the clause against the query
        $this->clauses[$this->table->alias][] = [$delimiter, $column, $operator, $value];

        return $this;
    }

    /**
     * Add a where clause to the query, using OR as a delimiter.
     *
     * @param string      $column   The column to compare.
     * @param string|null $operator The operator to use for the clause.
     *                              A default of '=' is assumed, and if that
     *                              is the case, the value for comparison can
     *                              be placed here as a shorthand.
     * @param mixed       $value    The value to compare.
     *
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        // If only two arguments are passed, add an '=' as the operator
        if (func_num_args() === 2 && $operator !== null) {
            $value = $operator;

            return $this->where($column, '=', $value);
        }

        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Create two where clauses, testing if the provided column falls between
     * two provided values.
     *
     * @param string $column      The column to compare
     * @param mixed  $firstValue  The first values
     * @param mixed  $secondValue The second value
     * @param bool   $inclusive   Whether to use > or >= etc.
     *
     * @return $this
     */
    public function whereBetween($column, $firstValue, $secondValue, $inclusive = true)
    {
        $firstOperator  = '>';
        $secondOperator = '<';

        if ($inclusive) {
            $firstOperator  = '>=';
            $secondOperator = '<=';
        }

        $this->where($column, $firstOperator, $firstValue);
        $this->where($column, $secondOperator, $secondValue);

        return $this;
    }

    /**
     * Add an on clause to the query.
     *
     * @param string      $column    The column to compare.
     * @param string|null $operator  The operator to use for the clause.
     *                               A default of '=' is assumed, and if that
     *                               is the case, the value for comparison can
     *                               be placed here as a shorthand.
     * @param mixed       $value     The value to compare.
     * @param string      $delimiter The delimiter used for imploding clauses.
     *
     * @return $this
     */
    public function on($column, $operator = null, $value = null, $delimiter = 'AND')
    {
        // If only two arguments are passed, add an '=' as the operator
        if (func_num_args() === 2 && $operator !== null) {
            $value = $operator;

            return $this->on($column, '=', $value);
        }

        // If this is the first where clause, force the ON keyword
        if (count($this->onClauses) === 0) {
            $delimiter = 'ON';
        }

        // If the value is a subquery, set its parent parameter
        if ($value instanceof self) {
            $value->parent = $this;
        }

        // Store the clause against the query
        $this->onClauses[$this->table->alias][] = [$delimiter, $column, $operator, $value];

        return $this;
    }

    /**
     * Add an on clause to the query, using OR as a delimiter.
     *
     * @param string      $column   The column to compare.
     * @param string|null $operator The operator to use for the clause.
     *                              A default of '=' is assumed, and if that
     *                              is the case, the value for comparison can
     *                              be placed here as a shorthand.
     * @param mixed       $value    The value to compare.
     *
     * @return $this
     */
    public function orOn($column, $operator = null, $value = null)
    {
        return $this->on($column, $operator, $value, 'OR');
    }

    /**
     * Add an having clause to the query.
     *
     * @param string      $column    The column to compare.
     * @param string|null $operator  The operator to use for the clause.
     *                               A default of '=' is assumed, and if that
     *                               is the case, the value for comparison can
     *                               be placed here as a shorthand.
     * @param mixed       $value     The value to compare.
     * @param string      $delimiter The delimiter used for imploding clauses.
     *
     * @return $this
     */
    public function having($column, $operator = null, $value = null, $delimiter = 'AND')
    {
        // If only two arguments are passed, add an '=' as the operator
        if (func_num_args() === 2 && $operator !== null) {
            $value = $operator;

            return $this->having($column, '=', $value);
        }

        // If this is the first where clause, force the HAVING keyword
        if (count($this->havingClauses) === 0) {
            $delimiter = 'HAVING';
        }

        // If the value is a subquery, set its parent parameter
        if ($value instanceof self) {
            $value->parent = $this;
        }

        // Store the clause against the query
        $this->havingClauses[$this->table->alias][] = [$delimiter, $column, $operator, $value];

        return $this;
    }

    /**
     * Add an having clause to the query, using OR as a delimiter.
     *
     * @param string      $column   The column to compare.
     * @param string|null $operator The operator to use for the clause.
     *                              A default of '=' is assumed, and if that
     *                              is the case, the value for comparison can
     *                              be placed here as a shorthand.
     * @param mixed       $value    The value to compare.
     *
     * @return $this
     */
    public function orHaving($column, $operator = null, $value = null)
    {
        return $this->having($column, $operator, $value, 'OR');
    }

    /**
     * Add a join to the query.
     *
     * @param self|string $join The subquery containing clauses for the join.
     * @param string      $type The join type.
     *
     * @return $this
     */
    public function join($join, $type = self::LEFT_JOIN)
    {
        if (is_string($join)) {
            if (!isset($this->table->relationships[$join])) {
                throw new QuerySyntaxError('The relationship specified does not exist');
            }

            $relationship = $this->table->relationships[$join];

            return $this->join(
                self::table(Table::find($relationship->table, $join))
                    ->on($relationship->references, 'parent.'.$relationship->column)
            );
        }

        $alias = $join->table->alias;

        $join->type   = $type;
        $join->parent = $this;

        $this->joins[$alias] = $join;

        return $this;
    }

    /**
     * Add a left join to the query.
     *
     * @param self|string $join The subquery containing clauses for the join.
     *
     * @return $this
     */
    public function leftJoin($join)
    {
        return $this->join($join, self::LEFT_JOIN);
    }

    /**
     * Add a right join to the query.
     *
     * @param self|string $join The subquery containing clauses for the join.
     *
     * @return $this
     */
    public function rightJoin($join)
    {
        return $this->join($join, self::RIGHT_JOIN);
    }

    /**
     * Add an inner join to the query.
     *
     * @param self|string $join The subquery containing clauses for the join.
     *
     * @return $this
     */
    public function innerJoin($join)
    {
        return $this->join($join, self::INNER_JOIN);
    }

    /**
     * Set the fields used for ordering the query.
     *
     * @return $this
     */
    public function orderBy($field, $direction = 'ASC')
    {
        $this->orderFields[] = [$field, $direction];

        return $this;
    }

    /**
     * Set the fields used for grouping the query.
     *
     * @return $this
     */
    public function groupBy($field)
    {
        $this->groupFields[] = $field;

        return $this;
    }

    /**
     * Paginate a query.
     *
     * @param int $limit The limit for each page
     * @param int $page  The current page number
     *
     * @return $this
     */
    public function paginate($limit, $page = null)
    {
        // Add SQL_CALC_FOUND_ROWS hint to the query
        $this->type = self::SELECT_COUNT;

        // Set the limit
        $this->limit($limit);

        // If page is not passed directly, work it out
        if ($page === null) {
            $page = $this->getPage();
        }

        // Set the offset
        $offset = ($limit * ($page - 1));
        $this->offset($offset);

        return $this;
    }

    /**
     * Get the current page from the URL query string.
     *
     * @return int
     */
    private function getPage()
    {
        // Check the URL query string for the page
        if ($page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT)) {
            return $page;
        }

        // Use the first page as the default
        return 1;
    }

    /**
     * Set the limit for the query.
     *
     * @param int $limit The limit
     *
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = (int) $limit;

        return $this;
    }

    /**
     * Set the offset for the query.
     *
     * @param int $offset The offset
     *
     * @return $this
     */
    public function offset($offset)
    {
        $this->offset = (int) $offset;

        return $this;
    }

    /**
     * Changes query type to update, and defines the fields to be updated,
     * then executes the query.
     *
     * @param array $fields The fields to be updated
     *
     * @return bool
     */
    public function update(array $fields = [])
    {
        $this->type         = self::UPDATE;
        $this->updateFields = $fields;

        return $this->execute();
    }

    /**
     * Changes query type to insert, and defines the fields to be inserted,
     * then executes the query.
     *
     * @param array $fields The fields to be inserted
     *
     * @return bool
     */
    public function insert(array $fields = [])
    {
        $this->type         = self::INSERT;
        $this->insertFields = $fields;

        return $this->execute();
    }

    /**
     * Changes query type to delete, then executes the query.
     *
     * @param array $fields The fields to be deleted
     *
     * @return bool
     */
    public function delete()
    {
        $this->type         = self::DELETE;

        // We can't use aliases for columns in a delete query,
        // so we reset the table name here to avoid errors,
        // and re-store it in the cache.
        $this->table = Table::find($this->table->name, $this->table->name, $this->instance);

        return $this->execute();
    }

    /**
     * Execute the query against its instance.
     *
     * @return bool
     */
    public function execute()
    {
        $this->compile();

        return $this->instance->execute($this);
    }

    /**
     * Fetch the results for the query.
     *
     * @return Set|null Results or nothing
     */
    public function fetch()
    {
        $this->compile();

        return $this->instance->fetch($this);
    }

    /**
     * Get the first row returned by a Query.
     *
     * @return Model
     */
    public function first()
    {
        $this->limit(1);
        $result = $this->fetch();

        return $result->first();
    }

    /**
     * Find a row (or rows) by its primary key value.
     *
     * @param int|mixed $id The primary key value
     *
     * @return Model
     */
    public function find($id)
    {
        // If an array is passed, query against all IDs and return a collection.
        if (is_array($id)) {
            return $this->whereIn($this->table->primaryKey, $id)->fetch();
        }

        // Otherwise, do a primary key lookup and return the object directly.
        return $this->where($this->table->primaryKey, $id)->first();
    }

    /**
     * Compile the Query object into an executable SQL string.
     */
    public function compile()
    {
        $this->compiled = Builder::compile($this);
    }

    /**
     * Output the compiled, formatted SQL query to the screen.
     *
     * @return $this
     */
    public function dump($replaceVars = true)
    {
        // Compile the query
        $this->compile();
        $sql = $this->compiled;

        if ($replaceVars) {
            // Build an array of placeholders for each var in the compiled query
            foreach ($this->vars as $var) {
                // Wrap quotes around string vars
                if (is_string($var)) {
                    $var = '"'.$var.'"';
                }

                // Replace the ? placeholders with the actual value
                $sql = preg_replace('/\?/', $var, $sql, 1);
            }
        }

        // Output the formatted sql
        ob_start();
        echo \SqlFormatter::format($sql);

        return ob_get_clean();
    }
}
