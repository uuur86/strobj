<?php

namespace StrObj\Data;

use StrObj\Interfaces\DataStructures\DataInterface;

class DataCache
{
    /**
     * @var array
     */
    private array $paths;

    /**
     * Saves the value to cache for performance
     *
     * @param string    $path   requested path
     * @param mixed     $obj
     */
    public function save(string $path, $obj): void
    {
        $this->paths[$path] = $obj;
    }

    /**
     * Clears the cache
     *
     * @param string $path requested path
     */
    public function clear(string $path): void
    {
        unset($this->paths[$path]);
    }

    /**
     * Gets the stored value for performance. This function is used by get method.
     *
     * @param string $path requested path
     *
     * @return mixed
     */
    public function get(string $path): mixed
    {
        return $this->paths[$path];
    }

    /**
     * Searches the requested object with the given path.
     *
     * @param  string $path The path of the object or array to be accessed
     *
     * @return bool   Returns true if cache is exists
     *                otherwise returns false
     */
    public function isCached(string $path)
    {
        return isset($this->paths[$path]);
    }
}
