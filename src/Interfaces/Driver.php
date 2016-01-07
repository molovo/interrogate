<?php

namespace Molovo\Interrogate\Interfaces;

use Molovo\Interrogate\Database\Instance;
use Molovo\Interrogate\Query;
use Molovo\Interrogate\Table;

interface Driver
{
    /**
     * Create a new driver instance.
     *
     * @param array    $config   Connection information
     * @param Instance $instance The database instance using this driver
     */
    public function __construct(array $config = array(), Instance $instance);

    /**
     * Execute a query.
     *
     * @param Query $query The query to execute
     *
     * @return bool Success/Failure
     */
    public function execute(Query $query);

    /**
     * Execute a query, and return it's results.
     *
     * @param Query $query The query to execute
     *
     * @return Collection Query results
     */
    public function fetch(Query $query);

    /**
     * Retreive total found rows for last query.
     *
     * @return int The number of rows
     */
    public function foundRows();

    /**
     * Get the primary key field for a given table.
     *
     * @param Table $table The table
     *
     * @return string|null The primary key field name
     */
    public function primaryKeyForTable(Table $table);

    /**
     * Get the field names for a given table.
     *
     * @param Table $table The table
     *
     * @return string[] An array of field names
     */
    public function fieldsForTable(Table $table);

    /**
     * Get the relationship data for a given table.
     *
     * @param Table $table The table
     *
     * @return string[] An array of relationship data
     */
    public function relationshipsForTable(Table $table);
}
