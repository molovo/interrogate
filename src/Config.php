<?php

namespace Molovo\Interrogate;

use Molovo\Interrogate\Database;
use Molovo\Object\Object;

class Config extends Object
{
    /**
     * Get a config value.
     *
     * @param string $path The path of the value to get
     *
     * @return mixed The value
     */
    public static function get($key)
    {
        return Database::config()->valueForPath($key);
    }

    /**
     * Set a config value.
     *
     * @param string $path  The path of the value to set
     * @param mixed  $value The value to set
     *
     * @return mixed The value
     */
    public static function set($key, $value = null)
    {
        return Database::config()->setValueForPath($key, $value);
    }
}
