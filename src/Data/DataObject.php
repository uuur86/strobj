<?php

namespace StrObj\Data;

use ArrayIterator;
use RecursiveArrayIterator;
use StrObj\Helpers\DataParsers;
use StrObj\Interfaces\DataStructures\DataInterface;

class DataObject extends RecursiveArrayIterator implements DataInterface
{
    /**
     * @var DataPath[]
     */
    private array $paths = [];

    /**
     * Latest query path
     *
     * @var string
     */
    private ?string $currentPath = null;

    /**
     * Cache object
     *
     * @var DataCache
     */
    private DataCache $cache;

    /**
     * Constructor
     *
     * @param object|array $obj  The object to use
     */
    public function __construct($data)
    {
        parent::__construct($data);

        $this->cache = new DataCache();
    }

    /**
     * Init data object
     */
    public function pathInit(string $path): DataPath
    {
        $this->currentPath = $path;

        if (!isset($this->paths[$path])) {
            $this->paths[$path] = new DataPath($path);
        }

        return $this->paths[$path];
    }

    /**
     * Get latest query path
     *
     * @return string
     */
    public function getCurrentPath(): string
    {
        return $this->currentPath;
    }

    /**
     * Save data to cache
     *
     * @param string $path
     * @param mixed $value
     */
    public function cache(string $path, mixed $value): void
    {
        $this->cache->save($path, $value);
    }

    /**
     * Get value if exists, otherwise return null
     *
     * @param string $path
     *
     * @return mixed
     */
    public function get(string $path): mixed
    {
        if ($this->cache->isCached($path)) {
            return $this->cache->get($path);
        }

        $value = $this->query($path);

        $this->cache($path, $value);

        return $value;
    }

    /**
     * Query data with given path
     */
    public function query(?string $path = null): mixed
    {
        if (strlen($path) === 0 || $path === '*') {
            return $this->getArrayCopy();
        }

        $this->rewind();

        $path_arr = $this->pathInit($path)->getArray();

        $data = $this;

        while ($key = current($path_arr)) {
            $next_key = next($path_arr);

            if ($key === '*') {
                return $data->getCols($next_key);
            }

            if (!$data->findKey($key)) {
                break;
            }

            if ($next_key && $data->hasChildren()) {
                $data = $data->getChildren();
                continue;
            }

            break;
        }

        return $data->current();
    }

    /**
     * Set a value to the given path
     *
     * @param string $path
     * @param mixed $value
     */
    public function set(string $path, mixed $value): void
    {
        $data       = &$this;
        $set_key    = null;
        $create_key = null;
        $path_      = $this->pathInit($path);

        if (!$path_) {
            return;
        }

        $this->cache($path, $value);

        $path_arr = $path_->getArray();

        foreach ($path_arr as $key) {
            if (!$data->findKey($key)) {
                $create_key = $key;
                break;
            }

            $set_key = $key;

            if (!$data->hasChildren()) {
                break;
            }

            $data = $data->getChildren();
        }

        // Warning! DO NOT REMOVE! This solution contains a bug fix for php
        // https://github.com/php/php-src/issues/10519
        // due to the fact that the php reference bug appears when new keys are created
        if (!empty($create_key)) {
            $path_arr_r = array_reverse($path_arr);

            if ($path_arr_r[0] === $create_key) {
                $data->offsetSet($create_key, $value);
                return;
            }

            foreach ($path_arr_r as $key) {
                if ($key === $create_key) {
                    break;
                }

                $value = [$key => $value];
            }

            if (is_array($value)) {
                $data->offsetSet($create_key, $value);
                $this->offsetSet($set_key, $data->getArrayCopy());

                return;
            }
        }

        $data->offsetSet($set_key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($index, $val)
    {
        if (is_array($val)) {
            $val = (object)$val;
        }

        parent::offsetSet($index, $val);
    }

    /**
     * {@inheritdoc}
     */
    public function getCols(string $colname): array
    {
        return array_column($this->getArrayCopy(), $colname);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->getArrayCopy();
    }

    /**
     * Find path
     *
     * @param string $key
     *
     * @return bool
     */
    public function findKey(string $key): bool
    {
        while ($this->key() !== $key && $this->valid()) {
            $this->next();
        }

        return $this->key() === $key;
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return (string)parent::key();
    }

    /**
     * Data is exists or not
     *
     * @param string $path
     *
     * @return bool
     */
    public function has(string $path): bool
    {
        return in_array($path, $this->paths, true);
    }
}
