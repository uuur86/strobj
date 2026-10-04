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

declare(strict_types=1);

namespace StrObj\Data;

use ArrayIterator;
use StrObj\Helpers\DataParsers;

class DataPath extends ArrayIterator
{
    /**
     * Data Parser trait
     */
    use DataParsers;

    /**
     * @var string
     */


    private string $path;
    /**
     * Parses a path while retaining the original string.
     *
     * @param string $path The slash-separated path.
     */
    public function __construct(string $path)
    {
        $this->path = $path;
        parent::__construct($this->parsePath($path));
    }

    /**
     * Initialize a path
     *
     * @param string        $path
     *
     * @return DataPath
     */
    public static function init(string $path)
    {

        return new self($path);
    }

    /**
     * Get raw path
     *
     * @return string
     */
    public function getRaw(): string
    {
        return $this->path;
    }

    /**
     * Get path array
     *
     * @return array
     */
    public function getArray(): array
    {
        return $this->getArrayCopy();
    }

    /**
     * Find all sub branches
     *
     * @return array
     */
    public function getBranches()
    {

        $branches = [];
        $prefix = '';

        foreach ($this->getArray() as $segment) {
            $prefix = $prefix === '' ? $segment : $prefix . '/' . $segment;
            $branches[] = $prefix;
        }

        return $branches;
    }

    /**
     * Path exists
     *
     * @param string $path
     */
    public function exists(string $path): bool
    {
        return in_array($this->normalizePath($path), $this->getBranches(), true);
    }
}
