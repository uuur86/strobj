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
     * Init data object
     */
    public function pathInit(string $path): DataPath
    {
        if (!isset($this->paths[$path])) {
            $this->paths[$path] = new DataPath($path);
        }

        return $this->paths[$path];
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
        $this->rewind();
        $path_ = $this->pathInit($path);

        if (!$path_) {
            return null;
        }

        $path_arr = $path_->getArray();
        $branched = false;

        $data = $this;

        foreach ($path_arr as $key) {
            if ($key === '*') {
                $branched = true;
                continue;
            }

            if ($branched) {
                return $data->getCols($key);
            }

            if (!$data->findKey($key)) {
                break;
            }

            if ($data->hasChildren()) {
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
     */
    public function has(string $path): bool
    {
        return in_array($path, $this->paths, true);
    }
}
