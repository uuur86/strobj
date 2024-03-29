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

class DataCache
{
    /**
     * @var array
     */
    private array $paths = [];

    /**
     * Saves the value to cache for performance
     *
     * @param string    $path   requested path
     * @param mixed     $data
     */
    public function save(string $path, $data): void
    {
        if (is_array($data)) {
            $paths = DataPath::init($path)->findPaths($path, $data);
            $this->addPaths($paths);
        }

        $this->setPath($path, $data);
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
    public function get(string $path)
    {
        return $this->paths[$path];
    }

    public function setPath(string $path, $data): void
    {
        if (substr_count($path, '*') < 1) {
            $this->paths[$path] = $data;
        }
    }

    protected function addPaths(array $paths): void
    {
        $this->paths = array_merge($this->paths, $paths);
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
