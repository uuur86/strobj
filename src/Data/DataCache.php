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
    private function save(string $path, $obj): void
    {
        $this->paths[$path] = $obj;

        $path = explode('/', $path);

        if (!is_array($path)) {
            return;
        }

        while (count($path) > 1) {
            array_pop($path);
            $path_txt = implode('/', $path);
            $this->save($path_txt, $obj);
        }
    }

    /**
     * Gets the stored value for performance. This function is used by get method.
     *
     * @param string $path requested path
     *
     * @return mixed
     */
    private function get(string $path): mixed
    {
        if (!$this->isPathExists($path)) {
            return false;
        }

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
        return $this->isPathExists($path);
    }

    /**
     * Checks the path is exists in cache
     *
     * @param string $path
     *
     * @return bool
     */
    private function isPathExists(string $path): bool
    {
        return isset($this->paths[$path]);
    }
}
