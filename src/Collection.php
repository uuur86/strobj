<?php

/**
 * @package strobj
 */

namespace StrObj;

use ArrayIterator;
use Iterator;
use JsonSerializable;
use StrObj\Data\DataObject;
use StrObj\Data\DataPath;

class Collection extends ArrayIterator implements Iterator, JsonSerializable
{
    /**
     * @var DataPath[]
     */
    private array $pathInfo = [];

    /**
     * @var DataObject[]
     */
    private array $pathData = [];

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct([]);
    }

    /**
     * Init new collection
     */
    public static function init()
    {
        return new self();
    }

    /**
     * Add new data to collection, if path exists, it will be overwritten
     *
     * @param DataPath   $path
     * @param DataObject $value
     */
    public function set(DataPath $path, DataObject $value)
    {
        $path_key = $path->getPath();

        $this->pathInfo[$path_key] = $path;
        $this->pathData[$path_key] = $value;

        $this->offsetSet($path_key, $value);
    }

    /**
     * currentKey getter function
     *
     * @param string $key path key
     *
     * @return DataObject
     */
    public function get(string $key): DataObject
    {
        if ($this->offsetExists($key)) {
            return $this->offsetGet($key);
        }

        return new DataObject([]);
    }

    /**
     * Get result data as array
     *
     * @param array $default [optional]
     *
     * @return array
     */
    public function toArray(array $default = [])
    {
        return $this->getArrayCopy() ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this;
    }
}
