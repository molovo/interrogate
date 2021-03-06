<?php

namespace Molovo\Interrogate\Driver\Pdo;

use Molovo\Interrogate\Config;
use Molovo\Interrogate\Database\Instance;
use PDO;

class Mysql extends Base
{
    /**
     * Default configuration values for a mysqli connection.
     *
     * @var array
     */
    private $defaultConfig = [
        'username' => 'root',
        'hostname' => 'localhost',
        'port'     => 3306,
    ];

    /**
     * @inheritDoc
     */
    public function __construct(Config $config, Instance $instance)
    {
        $config = new Config(array_merge($this->defaultConfig, $config->toArray()));

        $this->instance = $instance;

        if (isset($config->socket)) {
            $dsn = 'mysql:'.'unix_socket='.$config->socket.';'
                         .'dbname='.$config->database;
        }

        if (!isset($config->socket)) {
            $dsn = 'mysql:'.'host='.$config->hostname.';'
                           .'port='.$config->port.';'
                           .'dbname='.$config->database;
        }

        $this->client = new PDO(
            $dsn,
            $config->username,
            $config->password
        );
    }
}
