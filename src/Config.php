<?php

namespace Molovo\Interrogate;

use Dotenv\Dotenv;

class Config
{
    /**
     * Private store of configuration values.
     *
     * @var array
     */
    private static $_vars = [];

    /**
     * Retrieve and cache config variables.
     *
     * @method vars
     *
     * @return array The retreived config
     */
    private static function &vars()
    {
        if (static::$_vars === null) {
            return static::$_vars;
        }

        $dotenv = new Dotenv($_SERVER['DOCUMENT_ROOT']);
        $dotenv->load();

        foreach ($_ENV as $key => $value) {
            if (strpos($key, 'INTERROGATE') === 0) {
                $bits = explode('_', $key);

                list($namespace, $connection, $param) = $bits;

                $connection = strtolower($connection);
                $param      = strtolower($param);

                static::$_vars[$connection][$param] = $value;
            }
        }

        return static::$_vars;
    }

    /**
     * Retreive the value for a given key.
     *
     * @method get
     *
     * @param string $key The key to fetch
     *
     * @return mixed|null The retreived value
     */
    public static function get($key)
    {
        $vars = static::vars();

        if (isset($vars[$key])) {
            return $vars[$key];
        }

        return;
    }

    /**
     * Set the value for a given key.
     *
     * @method set
     *
     * @param string $key   The key to set
     * @param mixed  $value The value to set
     */
    public static function set($key, $value)
    {
        $vars = &static::vars();

        return $vars[$key] = $value;
    }
}
