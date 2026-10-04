<?php

/**
 * This file is part of the StrObj package.
 *
 * (c) Uğur Biçer <contact@fyndsoft.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package  StrObj
 * @version  GIT: <git_id>
 * @link     https://github.com/uuur86/strobj
 */

declare(strict_types=1);

namespace StrObj\Data;

/**
 * Path/value store kept for API compatibility
 *
 * @deprecated 3.0 Reads always resolve current data and no longer consult this cache.
 *             It will be removed in the next major version.
 */
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
            $this->addPaths(DataPath::init($path)->findPaths($path, $data));
        }

        $this->setPath($path, $data);
    }

    /**
     * Clears the cache
     *
     * @param string $path Requested path.
     */
    public function clear(string $path): void
    {
        unset($this->paths[$path]);
    }

    /** Clears all entries without changing the existing overridable clear() signature. */
    public function clearAll(): void
    {
        $this->paths = [];
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
        return $this->paths[$path] ?? null;
    }

    /**
     * Stores concrete paths, including null and false values.
     *
     * @param string $path Concrete path.
     * @param mixed  $data Value to cache.
     *
     * @return void
     */
    public function setPath(string $path, $data): void
    {
        if (strpos($path, '*') === false) {
            $this->paths[$path] = $data;
        }
    }

    /**
     * Merges projected entries without renumbering numeric keys.
     *
     * @param array $paths Path/value entries.
     *
     * @return void
     */
    protected function addPaths(array $paths): void
    {
        $this->paths = array_replace($this->paths, $paths);
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
        return array_key_exists($path, $this->paths);
    }
}
