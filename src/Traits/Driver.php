<?php

namespace Molovo\Interrogate\Traits;

use Doctrine\Common\Inflector\Inflector;
use Molovo\Interrogate\Collection;
use Molovo\Interrogate\Config;
use Molovo\Interrogate\Model;
use Molovo\Interrogate\Query;

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
     * Convert an array of data into a Model object, and store it
     * in the provided collection.
     *
     * @method packageModel
     *
     * @param array      $data       The data to package
     * @param Collection $collection The collection to store the model in
     * @param Query      $query      The query which produced the data
     */
    protected function packageModel(array $data = [], Collection &$collection, Query $query)
    {
        $primary = $query->table->primaryKey;

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

            $config    = Config::get($this->table->instance->name);
            $namespace = $config['model_namespace'];

            if ($namespace === null) {
                $config = Config::get('default');
                $config['model_namespace'];

                if ($namespace === null) {
                    $namespace = 'Models';
                }
            }

            $class_name = Inflector::singularize($this->table->name);
            $class_name = Inflector::classify($namespace.'\\'.$class_name);

            if (!class_exists($class_name)) {
                $class_name = Model::class;
            }

            // Create a new model using the result data.
            $model         = new Model($query->table, $modelData, $this->instance);
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
            $joinAlias      = $joinQuery->table->alias;

            // Package the results for this join into models, and attach
            // them to the nested collection.
            $this->packageModel($data[$joinAlias], $joinCollection, $joinQuery);

            // Attach the nested collection to the parent model
            $this->tempModels[$hash]->{$joinAlias} = $joinCollection;
        }
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
        $prepared = [];
        foreach ($data as $key => $value) {
            $bits    = explode('___', $key);
            $last    = explode('.', array_pop($bits));
            foreach ($last as $lastItem) {
                $bits[] = $lastItem;
            }
            $pointer = &$prepared;
            foreach ($bits as $bit) {
                if (is_array($pointer)) {
                    if (!isset($pointer[$bit])) {
                        $pointer[$bit] = [];
                        $pointer       = &$pointer[$bit];
                        continue;
                    }
                    $pointer = &$pointer[$bit];
                }
            }
            $pointer = $value;
        }

        return $prepared;
    }
}
