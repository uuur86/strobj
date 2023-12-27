<?php

/**
 * This file is part of the StrObj package.
 *
 * (c) Uğur Biçer <contact@codeplus.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package  StrObj
 * @version  GIT: <git_id>
 * @link     https://github.com/uuur86/strobj
 */

namespace StrObj\Data;

use RecursiveArrayIterator;
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
     * @param array|object $obj The object to use
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
     * @param mixed  $value
     */
    public function cache(string $path, $value): void
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
    public function get(string $path)
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
     *
     * @param string $path string path of the requested value - default null
     *
     * @return mixed
     */
    public function query(?string $path = null)
    {
        if (strlen($path) === 0 || $path === '*') {
            return $this->getArrayCopy();
        }

        $this->rewind();

        $path_arr = $this->pathInit($path)->getArray();

        $data = $this;

        while (false !== ($key = current($path_arr))) {
            $next_key = next($path_arr);
            $next_key = $next_key !== false ? (string) $next_key : false;

            if ($key === '*') {
                return $data->getCols($next_key);
            }

            // return null If the requested key doesn't exists
            if (! $data->findKey((string) $key)) {
                return null;
            }

            if ($next_key === false) {
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
     * @param mixed  $value
     */
    public function set(string $path, $value): void
    {
        // Current data object reference
        $data = &$this;

        // The name of the parent key index
        $set_key = null;

        // The key name to create when needed
        $create_key = null;

        // Returned DataPath object
        $path_ = $this->pathInit($path);

        if (!$path_) {
            return;
        }

        // Add given value to the cache
        $this->cache($path, $value);

        // Get path as an array
        $path_arr = $path_->getArray();

        // Roam the path
        foreach ($path_arr as $key) {
            // Break the loop when the key doesn't exists
            if (!$data->findKey($key)) {
                $create_key = $key;
                break;
            }

            // We assign the current key index sequence each time
            // to find the last index that is hasn't a child object.
            $set_key = $key;

            // If object hasn't children, it means we found the last key
            if (!$data->hasChildren()) {
                break;
            }

            // Continue if it has a children
            $data = $data->getChildren();
        }

        // Warning! DO NOT REMOVE! This solution contains a bug fix for php
        // due to the fact that the php reference bug appears when new keys are created
        // https://github.com/php/php-src/issues/10519
        //
        // Problem solved in php 8.3.1
        // https://github.com/php/php-src/commit/49b2ff5dbb94b76b265fd5909881997e1d95c6b3
        if (! empty($create_key)) {
            // Reverse the array starting from the child to the parent.
            $path_arr_r = array_reverse($path_arr);

            // If last key equals to the key that will create, then set and finish the process
            if ($path_arr_r[0] === $create_key) {
                $data->offsetSet($create_key, $value);
                return;
            }

            // Prepare $value when searching for array index that does not exist
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
    public function offsetSet($index, $val): void
    {
        if (is_array($val)) {
            $val = (object) $val;
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
    public function jsonSerialize(): array
    {
        return $this->getArrayCopy();
    }

    /**
     * To array function
     *
     * @return array
     */
    public function toArray(): array
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
    public function key(): string
    {
        return (string) parent::key();
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
