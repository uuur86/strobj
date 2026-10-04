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

namespace StrObj\Interfaces\DataStructures;

use Iterator;
use JsonSerializable;
use RecursiveIterator;

/**
 * Path-aware data container contract.
 */
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
