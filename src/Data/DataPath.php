<?php

namespace StrObj\Data;

use ArrayIterator;
use Iterator;
use JsonSerializable;

use StrObj\Helpers\DataParsers;
use StrObj\Interfaces\DataStructures\DataInterface;

class DataPath extends ArrayIterator implements Iterator
{
    /**
     * Parser trait
     * parsePath method
     */
    use DataParsers;

    /**
     * @var string
     */
    private string $path = '';

    /**
     * @var array
     */
    private array $map = [];

    public function __construct(string $path)
    {
        $this->path = $path;

        $path_arr = $this->parsePath($path);

        parent::__construct($path_arr);
    }

    /**
     * Inıt path
     *
     * @param string        $path
     * @param DataInterface $data
     *
     * @return ObjPath
     */
    public static function init(string $path)
    {
        return new DataPath($path);
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
     */
    public function getBranches()
    {
        $path        = $this->getArrayCopy();
        $branches    = &$this->map;
        $branch_path = array_shift($path);
        $branches[]  = $branch_path;

        while ($key = current($path)) {
            $branch_path .= '/' . $key;
            $branches[] = $branch_path;

            next($path);
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
        return in_array($path, $this->map);
    }
}
