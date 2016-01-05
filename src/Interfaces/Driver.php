<?php

namespace Molovo\Interrogate\Interfaces;

use Molovo\Interrogate\Query;
use Molovo\Interrogate\Table;

interface Driver
{
    /**
     * Create a new driver instance.
     *
     * @method __construct
     *
     * @param array $config Connection information
     */
    public function __construct(array $config = array());

    /**
     * Execute a query.
     *
     * @method execute
     *
     * @param Query $query The query to execute
     *
     * @return Set Query results
     */
    public function execute(Query $query);

    /**
     * Retreive total found rows for last query.
     *
     * @method foundRows
     *
     * @return int The number of rows
     */
    public function foundRows();

    /**
     * Get the primary key field for a given table.
     *
     * @method primaryKeyForTable
     *
     * @param Table $table The table
     *
     * @return string|null The primary key field name
     */
    public function primaryKeyForTable(Table $table);

    /**
     * Get the field names for a given table.
     *
     * @method primaryKeyForTable
     *
     * @param Table $table The table
     *
     * @return string[] An array of field names
     */
    public function fieldsForTable(Table $table);
}
