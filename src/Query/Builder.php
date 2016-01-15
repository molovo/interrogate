<?php

namespace Molovo\Interrogate\Query;

use Molovo\Interrogate\Exceptions\QueryExecutionException;
use Molovo\Interrogate\Query;
use SqlFormatter;

class Builder
{
    /**
     * The compiled query string in progress.
     *
     * @var string
     */
    public $queryString = '';

    /**
     * The query being compiled.
     *
     * @var Query
     */
    private $query = null;

    /**
     * Tables being used by the current query.
     *
     * @var Table[]
     */
    private $tables = [];

    /**
     * Fields accessed by the current query.
     *
     * @var string[]
     */
    private $fields = [];

    /**
     * Subqueries being used by the current query.
     *
     * @var Query[]
     */
    private $subqueries = [];

    /**
     * Whether or not the first clause has been added. Used to prevent where
     * clauses on joins from adding additional WHERE keywords.
     *
     * @var bool
     */
    private $firstWhereClauseAdded = false;

    /**
     * Create a new builder instance, and compile the query.
     *
     * @method compile
     *
     * @param Query $query The query object to compile
     *
     * @return string The compiled query string
     */
    public static function compile(Query $query)
    {
        $builder = new self($query);

        $builder->compileQuery();

        return $builder->queryString;
    }

    /**
     * Create a new query builder instance.
     *
     * @method __construct
     *
     * @param Query $query The query object to compile
     */
    public function __construct(Query $query)
    {
        $this->query = $query;
    }

    /**
     * Compile the attached query object.
     *
     * @method compileQuery
     */
    public function compileQuery()
    {
        // Always clear the slate
        $this->queryString = '';
        $this->query->vars = [];
        $this->tables      = [];
        $this->fields      = [];
        $this->subqueries  = [];

        $query             = &$this->query;
        $type              = &$query->type;

        // Store the table used by the query for use later
        if (!in_array($query->table->alias, $this->tables)) {
            $this->tables[$query->table->alias] = $query->table;
        }

        // Store the fields for the query for use later
        if (!isset($query->fields[$query->table->alias])) {
            $query->fields[$query->table->alias] = $query->table->fields;
        }

        // Prepare joins for compilation
        $this->prepareJoins();

        // Compile the query type
        $this->compileType();

        // For select queries, compile the fields being selected, then add
        // the table to the query
        if (in_array($type, [Query::SELECT, Query::SELECT_COUNT])) {
            $this->compileSelectFields();
            $this->compileTableString();
        }

        if ($type === Query::DELETE) {
            $this->compileTableString();
        }

        // For update queries, start with the table string, then compile
        // the fields to be updated.
        if ($type === Query::UPDATE) {
            $this->compileTableString();
            $this->compileUpdateFields();
        }

        // For insert queries, start with the table string, then compile
        // the fields to be inserted.
        if ($type === Query::INSERT) {
            $this->compileTableString();
            $this->compileInsertFields();
        }

        if ($type !== Query::INSERT) {
            // Compile any joins attached to the query
            $this->compileJoins();

            // Compile all the where clauses for the query (and any subqueries)
            $this->compileWhereClauses($this->query);

            // Compile group, having and order clauses
            $this->compileGroupFields();
            $this->compileClauses($this->query->havingClauses);
            $this->compileOrderFields();

            // Compile limit and offset
            $this->compileLimit();
            $this->compileOffset();
        }
    }

    /**
     * Add the correct query keyword to the query string,
     * based on the query object's type.
     *
     * @method compileType
     */
    private function compileType()
    {
        switch ($this->query->type) {
            case Query::UPDATE:
                $this->queryString .= 'UPDATE ';
                break;
            case Query::INSERT:
                $this->queryString .= 'INSERT ';
                break;
            case Query::DELETE:
                $this->queryString .= 'DELETE ';
                break;
            case Query::SELECT_COUNT:
                $this->queryString .= 'SELECT SQL_CALC_FOUND_ROWS ';
                break;
            case Query::SELECT:
            default:
                $this->queryString .= 'SELECT ';
                break;
        }
    }

    /**
     * Compile the fields for a select query.
     *
     * @method compileSelectFields
     */
    private function compileSelectFields()
    {
        $fields = $this->query->fields;

        $compiledFields = [];
        foreach ($fields as $table => $columns) {
            foreach ($columns as $field) {
                $alias = null;

                if (is_array($field)) {
                    list($field, $alias) = $field;
                }

                if (is_string($field)) {
                    if ($alias === null) {
                        $alias = $field;
                    }

                    if ($field !== '*') {
                        $field = '`'.$field.'`';
                    }

                    $field = '`'.$table.'`.'.$field;
                }

                if ($field instanceof Query) {
                    $field = $this->compileSubquery($field);
                }

                if ($alias !== null) {
                    $this->fieldAliases[$field] = $alias;
                    $field .= ' AS "'.$alias.'"';
                }

                $compiledFields[] = $field;
            }
        }

        $this->queryString .= implode(', ', $compiledFields);
    }

    /**
     * Compile the fields for an update query.
     *
     * @method compileUpdateFields
     */
    private function compileUpdateFields()
    {
        $fields         = $this->query->updateFields;
        $compiledFields = [];

        if (count($fields) > 0) {
            $this->queryString .= 'SET ';

            foreach ($fields as $key => $value) {
                $compiledFields[] = '`'.$this->query->table->alias.'`.`'.$key.'` = ?';
                $this->appendVar($value);
            }
        }

        $this->queryString .= implode(', ', $compiledFields);
    }

    /**
     * Compile the fields for an insert query.
     *
     * @method compileInsertFields
     */
    private function compileInsertFields()
    {
        $fields         = $this->query->insertFields;
        $compiledFields = [];
        $compiledValues = [];

        if (count($fields) > 0) {
            foreach ($fields as $key => $value) {
                $compiledFields[] = '`'.$key.'`';
                $compiledValues[] = '?';
                $this->appendVar($value);
            }
        }

        if (!empty($compiledFields)) {
            $this->queryString .= ' ('.implode(',', $compiledFields).')';
        }

        if (!empty($compiledValues)) {
            $this->queryString .= ' VALUES ('.implode(',', $compiledValues).')';
        }
    }

    /**
     * Compile the table string for the query, including keywords.
     *
     * @method compileTableString
     */
    private function compileTableString()
    {
        $table = $this->query->table->name;
        $alias = $this->query->table->alias;

        switch ($this->query->type) {
            case Query::INSERT:
                $this->queryString .= ' INTO `'.$table.'`';
                break;
            case Query::LEFT_JOIN:
            case Query::RIGHT_JOIN:
            case Query::INNER_JOIN:
            case Query::UPDATE:
                $this->queryString .= ' `'.$table.'`';
                break;
            case Query::SELECT:
            case Query::SELECT_COUNT:
            case Query::DELETE:
            default:
                $this->queryString .= ' FROM `'.$table.'`';
                break;
        }

        if ($alias !== $table) {
            $this->queryString .= ' '.$alias.' ';
        }
    }

    /**
     * Compile a nested query object, and inject its query string
     * into the current query.
     *
     * @method compileSubquery
     *
     * @param Query $query The nested query object
     *
     * @return string The compiled subquery string
     */
    private function compileSubquery(Query $query)
    {
        $this->subqueries[$query->table->alias] = $query;

        $query->compile();

        return '('.$query->compiled.')';
    }

    private function prepareJoins()
    {
        foreach ($this->query->joins as $alias => $join) {
            $this->compileJoinFields($join, $alias);
        }
    }

    private function compileJoinFields(Query $join, $alias)
    {
        foreach ($join->joins as $subjoinAlias => $subjoin) {
            $this->compileJoinFields($subjoin, $alias.'___'.$subjoinAlias, $join);
        }
        if (!isset($join->fields[$join->table->alias])) {
            $join->fields[$join->table->alias] = $join->table->fields;
        }
        if (!in_array($join->table->alias, $this->tables)) {
            $this->tables[$join->table->alias] = $join->table;
        }
        foreach ($join->fields[$join->table->alias] as &$field) {
            $fieldAlias = $field;
            if (is_array($field)) {
                list($field, $fieldAlias) = $field;
            }
            $field = [$field, $alias.'.'.$fieldAlias];
        }
        $join->parent->fields = array_merge($join->parent->fields, $join->fields);
        $join->fields         = [];
    }

    private function compileJoins()
    {
        foreach ($this->query->joins as $join) {
            $this->compileJoin($join);
        }
    }

    private function compileJoin(Query $join)
    {
        $this->subqueries[$join->table->alias] = $join;

        switch ($join->type) {
            case Query::RIGHT_JOIN:
                $this->queryString .= ' RIGHT JOIN ';
                break;
            case Query::INNER_JOIN:
                $this->queryString .= ' INNER JOIN ';
                break;
            case Query::LEFT_JOIN:
            default:
                $this->queryString .= ' LEFT JOIN ';
                break;
        }

        $this->queryString .= '`'.$join->table->name.'`';

        if ($join->table->alias !== $join->table->name) {
            $this->queryString .= ' '.$join->table->alias;
        }

        $this->compileClauses($join->onClauses);

        foreach ($join->joins as $subjoin) {
            $this->compileJoin($subjoin);
        }
    }

    private function compileClauses(array $clauses)
    {
        foreach ($clauses as $table => $tableClauses) {
            foreach ($tableClauses as $clause) {
                list($delimiter, $column, $operator, $value) = $clause;

                if ($this->firstWhereClauseAdded && $delimiter === 'WHERE') {
                    $delimiter = 'AND';
                }

                $column = $this->formatValueStrings($column, $table);
                $value  = $this->formatValueStrings($value, $table);

                $this->queryString .= " $delimiter $column $operator $value ";
            }
        }
    }

    private function formatValueStrings($value, $tableAlias = null)
    {
        $table = $tableAlias ? $this->tables[$tableAlias] : $this->query->table;

        if ($value instanceof Query) {
            $value = $this->compileSubquery($value);

            return $value;
        }

        if (is_string($value)) {
            $bits   = explode('.', $value);
            $column = $bits[count($bits) - 1];

            if (count($bits) === 1 && $table->hasField($column)) {
                return '`'.$table->alias.'`.`'.$column.'`';
            }

            if (count($bits) > 1 && isset($this->subqueries[$table->alias])) {
                $query = $this->subqueries[$table->alias];

                if ($query !== null) {
                    foreach (array_reverse($bits) as $bit) {
                        if ($bit === 'parent') {
                            $query = $query->parent;
                        }
                    }

                    if ($query->table->hasField($column)) {
                        return '`'.$query->table->alias.'`.`'.$column.'`';
                    }
                }
            }

            if (count($bits) === 2 && isset($this->subqueries[$bits[0]])) {
                $tableAlias = $bits[0];
                $query      = $this->subqueries[$tableAlias];

                if ($query !== null) {
                    foreach (array_reverse($bits) as $bit) {
                        if ($bit === 'parent') {
                            $query = $query->parent;
                        }
                    }

                    if ($query->table->hasField($column)) {
                        return '`'.$query->table->alias.'`.`'.$column.'`';
                    }
                }
            }
        }

        $this->appendVar($value);

        return '?';
    }

    private function compileWhereClauses(Query $query)
    {
        $this->compileClauses($query->clauses);
        $this->firstWhereClauseAdded = true;

        foreach ($query->joins as $join) {
            $this->compileWhereClauses($join);
        }
    }

    private function compileOrderFields()
    {
        $fields = $this->query->orderFields;

        foreach ($fields as &$field) {
            list($fieldName, $direction) = $field;

            $fieldName = $this->formatValueStrings($fieldName);

            $field     = $fieldName.' '.$direction;
        }

        if (count($fields) !== 0) {
            $this->queryString .= ' ORDER BY '.implode(', ', $fields);
        }
    }

    private function compileGroupFields()
    {
        $fields = $this->query->groupFields;

        foreach ($fields as &$field) {
            $field = $this->formatValueStrings($field);
        }

        if (count($fields) !== 0) {
            $this->queryString .= ' GROUP BY '.implode(', ', $fields);
        }
    }

    private function compileLimit()
    {
        $limit = $this->query->limit;

        if ($limit !== null) {
            $this->queryString .= ' LIMIT ?';
            $this->appendVar($limit);
        }
    }

    private function compileOffset()
    {
        $offset = $this->query->offset;

        if ($offset !== null) {
            $this->queryString .= ' OFFSET ?';
            $this->appendVar($offset);
        }
    }

    private function appendVar($value)
    {
        if ($this->query->parent === null) {
            return $this->query->vars[] = $value;
        }

        $query = $this->query;

        while ($query->parent !== null) {
            $query = $query->parent;
        }

        $query->vars[] = $value;
    }
}
