<?php

namespace Molovo\Interrogate;

use Molovo\Interrogate\Database;

class Config
{
    /**
     * The stored config values.
     *
     * @var stdClass
     */
    private $values = null;

    /**
     * Create a new Config object.
     *
     * @param array $values The config values
     */
    public function __construct(array $values = [])
    {
        foreach ($values as &$value) {
            if (is_array($value)) {
                $value = new self($value);
            }
        }

        $this->values = (object) $values;
    }

    /**
     * Get a config value.
     *
     * @param string $key The key of the value to get
     *
     * @return mixed The value
     */
    public function __get($key)
    {
        if (isset($this->values->{$key})) {
            return $this->values->{$key};
        }

        if ($value = $this->valueForPath($key)) {
            return $value;
        }

        return;
    }

    /**
     * Return the config as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return (array) $this->values;
    }

    /**
     * Get a value for a nested path.
     *
     * @param string $path The path to fetch
     *
     * @return mixed The value
     */
    public function valueForPath($path)
    {
        $bits = explode('.', $path);

        $value = $this->values;
        foreach ($bits as $bit) {
            if (is_object($value)) {
                $value = $value->{$bit};
                continue;
            }

            return;
        }

        return $value;
    }

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
}
