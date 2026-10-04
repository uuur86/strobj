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

namespace StrObj;

use PHPUnit\Framework\TestCase;
use StrObj\Data\DataPath;

class TestDataPath extends TestCase
{
    public function testPathes()
    {
        $this->assertSame(DataPath::init('0/age')->getArray(), ['0', 'age']);
    }
}
