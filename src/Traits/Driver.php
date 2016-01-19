<?php

namespace Molovo\Interrogate\Traits;

use Molovo\Interrogate\Collection;
use Molovo\Interrogate\Config;
use Molovo\Interrogate\Model;
use Molovo\Interrogate\Query;
use Molovo\Str\Str;

trait Driver
{
    /**
     * The database instance fulfilled by this driver.
     *
     * @var Molovo\Interrogate\Instance
     */
    protected $instance = null;

    /**
     * An array for temporarily storing models whilst looping through results.
     *
     * @var Model[]
     */
    protected $tempModels = [];

    /**
     * A cache of table names mapped to model classnames.
     *
     * @var string[]
     */
    protected static $modelClassCache = [];

    /**
     * Convert an array of data into a Model object, and store it
     * in the provided collection.
     *
     * @method packageModel
     *
     * @param array      $data       The data to package
     * @param Collection $collection The collection to store the model in
     * @param Query      $query      The query which produced the data
     */
    protected function packageModel(array $data, Collection &$collection, Query $query)
    {
        $modelClass = $this->getModelClass($query);

        if (!($primary = $query->table->primaryKey)) {
            $primary = $modelClass::primaryKeyColumn();
        }

        // Get the current hash of the collection, so that we can compare
        // models within it when looping through results.
        $hash = spl_object_hash($collection);

        // If the primary key is different from the previous one, we have
        // finished collating data and can store the data in an model.
        if (!isset($this->tempModels[$hash]) || $data[$primary] !== $this->tempModels[$hash]->{$primary}) {
            $modelData = [];

            // Loop through each of the fields in the result data, excluding
            // those belonging to a join, and store them in a new array.
            foreach ($data as $key => $value) {
                if (!is_array($value)) {
                    $modelData[$key] = $value;
                }
            }

            // Create a new model using the result data.
            $model         = new $modelClass($query->table, $modelData, $this->instance);
            $model->stored = true;

            // Store the model for reference later
            $this->tempModels[$hash] = $model;

            // Attach the model to the collection
            $collection->attach($model);
        }

        // Loop through each of the joins on the query, so that we can create
        // nested collections for each of them.
        foreach ($query->joins as $joinQuery) {
            // Create a new collection for storing models against the parent.
            $joinCollection = new Collection;

            // Get the join alias, so that we can use it to store
            // the collection against a property on the parent model.
            $joinAlias = $joinQuery->table->alias;

            // Get the data for this join
            $joinData = $data[$joinAlias];

            // Check that the data doesn't just contain null values - if it does
            // then a row wasn't found so we don't create a model.
            if ($this->checkDataNotEmpty($joinData)) {
                // Package the results for this join into models, and attach
                // them to the nested collection.
                $this->packageModel($joinData, $joinCollection, $joinQuery);
            }

            // Attach the nested collection to the parent model
            $this->tempModels[$hash]->{$joinAlias} = $joinCollection;
        }
    }

    /**
     * Work the model class based on the table name, and cache it for later.
     *
     * @param Query $query The query to get a model for
     *
     * @return string The name of a model class
     */
    protected function getModelClass(Query $query)
    {
        if ($query->model !== null) {
            return $query->model;
        }

        if (isset(static::$modelClassCache[$query->table->name])) {
            return static::$modelClassCache[$query->table->name];
        }

        $namespace = null;
        $config    = Config::get($query->table->instance->name);

        if (isset($config->model_namespace)) {
            $namespace = $config->model_namespace;
        }

        if ($namespace === null) {
            $config = Config::get('default');

            if (isset($config->model_namespace)) {
                $namespace = $config->model_namespace;
            }

            if ($namespace === null) {
                $namespace = 'Models';
            }
        }

        $modelClass = Str::singularize($query->table->name);
        $modelClass = Str::camelCaps($namespace.'\\'.$modelClass);

        if (!class_exists($modelClass)) {
            $modelClass = Model::class;
        }

        return static::$modelClassCache[$query->table->name] = $modelClass;
    }

    /**
     * Check that a dataset contains more than just null values.
     *
     * @param array $data The dataset to check
     *
     * @return bool
     */
    protected function checkDataNotEmpty($data)
    {
        foreach ($data as $value) {
            if ($value !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prepare a row of query results for packaging as a model.
     *
     * @method prepareData
     *
     * @param array $data The data to package
     *
     * @return array The prepared data
     */
    protected function prepareData(array $data = [])
    {
        $prepared    = [];
        $lastPath    = null;
        $lastPointer = null;
        foreach ($data as $key => $value) {
            $bits = explode('.', $key);

            if (count($bits) === 1) {
                $prepared[$key] = $value;
                continue;
            }

            list($path, $column) = $bits;

            if ($path === $lastPath) {
                $lastPointer[$column] = $value;
                continue;
            }

            $lastPath = $path;
            $bits     = explode('___', $path);
            $pointer  = &$prepared;
            foreach ($bits as $bit) {
                if (is_array($pointer)) {
                    if (!isset($pointer[$bit])) {
                        $pointer[$bit] = [];
                        $pointer       = &$pointer[$bit];
                        continue;
                    }
                    $pointer     = &$pointer[$bit];
                }
            }

            $pointer[$column] = $value;
            $lastPointer      = &$pointer;
        }

        return $prepared;
    }
}
