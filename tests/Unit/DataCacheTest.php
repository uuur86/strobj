<?php

declare(strict_types=1);

namespace StrObj\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StrObj\Data\DataCache;

final class DataCacheTest extends TestCase
{
    /** @dataProvider cachedValues */
    public function testConcreteValuesIncludingNullAreCached($value): void
    {
        $cache = new DataCache();
        self::assertFalse($cache->isCached('field'));
        self::assertNull($cache->get('field'));
        $cache->save('field', $value);
        self::assertTrue($cache->isCached('field'));
        self::assertSame($value, $cache->get('field'));
        $cache->clear('field');
        self::assertFalse($cache->isCached('field'));
    }

    public function cachedValues(): array
    {
        return [[null], [false], [0], ['0'], [''], [['nested' => 1]]];
    }

    public function testWildcardProjectionKeepsNamedAndSparseIndexes(): void
    {
        $cache = new DataCache();
        $cache->save('persons/*/age', ['u1' => 12, 5 => 21]);
        self::assertSame(12, $cache->get('persons/u1/age'));
        self::assertSame(21, $cache->get('persons/5/age'));
        self::assertFalse($cache->isCached('persons/*/age'));
        $cache->save('*', [5 => 'five', 8 => 'eight']);
        self::assertSame('five', $cache->get('5'));
        self::assertSame('eight', $cache->get('8'));
        self::assertFalse($cache->isCached('0'));
        $cache->clearAll();
        self::assertFalse($cache->isCached('5'));
        self::assertFalse($cache->isCached('persons/u1/age'));
    }

    public function testNumericConcretePathIsNotRenumbered(): void
    {
        $cache = new DataCache();
        $cache->save('5', ['a' => 1]);
        self::assertSame(['a' => 1], $cache->get('5'));
        self::assertFalse($cache->isCached('0'));
    }
}
