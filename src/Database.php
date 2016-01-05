<?php

namespace Molovo\Interrogate;

use Molovo\Interrogate\Config;
use Molovo\Interrogate\Database\Instance;
use Molovo\Interrogate\Exceptions\InvalidDriverException;
use Molovo\Interrogate\Interfaces\Driver;
use Molovo\Interrogate\Table;

class Database
{
    /**
     * Query the database.
     *
     * @method query
     *
     * @param string        $table    The table to query
     * @param Instance|null $instance The database instance to use
     *
     * @return Query The query
     */
    public static function query($table, Instance $instance = null)
    {
        $instance = $instance ?: Instance::default_instance();

        if (!($table instanceof Table)) {
            $table = Table::find($table);
        }

        return new Query($table, $instance);
    }
}
