<?php

namespace Molovo\Interrogate;

use Dotenv\Dotenv;
use Molovo\Interrogate\Config;

class Database
{
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
}
