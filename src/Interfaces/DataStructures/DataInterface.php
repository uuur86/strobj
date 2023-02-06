<?php

namespace StrObj\Interfaces\DataStructures;

use Iterator;
use JsonSerializable;
use RecursiveIterator;

interface DataInterface extends JsonSerializable, Iterator, RecursiveIterator
{
    /**
     * Get array of column values
     *
     * @param string $colname
     *
     * @return array
     */
    public function getCols(string $colname): array;
}
