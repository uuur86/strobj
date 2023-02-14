<?php

/**
 * @package strobj
 */

namespace StrObj;

use ArrayIterator;
use Iterator;
use JsonSerializable;

class Collection extends ArrayIterator implements Iterator, JsonSerializable
{
    /**
     * @var ObjPath[]
     */
    private ObjPath $pathInfo = [];

    /**
     * @var ObjData[]
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
        return new static();
    }

    /**
     * Add new data to collection, if path exists, it will be overwritten
     *
     * @param ObjPath $path
     * @param ObjData $value
     */
    public function set(ObjPath $path, ObjData $value)
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
     * @return ObjData
     */
    public function get(string $key): ObjData
    {
        if ($this->offsetExists($key)) {
            return $this->offsetGet($key);
        }

        return new ObjData();
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
