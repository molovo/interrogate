<?php

namespace Molovo\Interrogate;

use Dotenv\Dotenv;
use Molovo\Interrogate\Config;
use Molovo\Interrogate\Database\Instance;

class Database
{
    /**
     * All active instances.
     *
     * @var Instance[]
     */
    private static $instances = [];

    /**
     * The config.
     *
     * @var Config|null
     */
    private static $config = null;

    /**
     * Bootstrap the interrogate library.
     *
     * @param array $config The config to initialize with
     */
    public static function bootstrap(array $config = [])
    {
        if (empty($config)) {
            $dotenv = new Dotenv($_SERVER['DOCUMENT_ROOT']);
            $dotenv->load();

            foreach ($_ENV as $key => $value) {
                if (strpos($key, 'INTERROGATE') === 0) {
                    $bits = explode('_', $key);

                    list($namespace, $connection, $param) = $bits;

                    $connection = strtolower($connection);
                    $param      = strtolower($param);

                    $config[$connection][$param] = $value;
                }
            }
        }

        static::$config = new Config($config);
    }

    /**
     * Get the database config.
     *
     * @return Config
     */
    public static function config()
    {
        return static::$config;
    }

    /**
     * Return a database instance.
     *
     * @return Instance
     */
    public static function instance($name = null)
    {
        $name = $name ?: 'default';

        if (isset(static::$instances[$name])) {
            return static::$instances[$name];
        }

        return static::$instances[$name] = new Instance($name, static::$config->{$name});
    }
}
