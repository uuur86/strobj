<?php

declare(strict_types=1);

namespace StrObj\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StrObj\Data\DataPath;

final class DataPathTest extends TestCase
{
    public function testPathsAreSplitIntoSegments(): void
    {
        self::assertSame(['0', 'age'], DataPath::init('0/age')->getArray());
    }
}
