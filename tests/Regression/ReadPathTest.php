<?php

declare(strict_types=1);

namespace StrObj\Tests\Regression;

use PHPUnit\Framework\TestCase;
use StrObj\Data\DataCache;
use StrObj\Data\DataObject;
use StrObj\StringObjects;

/**
 * Reads resolve current data in time proportional to the path depth.
 *
 * @see https://github.com/uuur86/strobj/issues/25
 */
final class ReadPathTest extends TestCase
{
    public function testLegacyReadsObserveExternalNestedMutations(): void
    {
        $input = json_decode('{"a":{"b":1}}');
        $object = StringObjects::instance($input);
        self::assertSame(1, $object->get('a/b'));
        $input->a->b = 2;
        self::assertSame(2, $object->get('a/b'));
    }

    public function testDistinctQueriesDoNotAccumulateState(): void
    {
        $object = new DataObject(array_fill(0, 200, ['v' => 1]));

        for ($index = 0; $index < 200; $index++) {
            self::assertSame(1, $object->get($index . '/v'));
        }

        $cache = (static function (DataObject $object): DataCache {
            return $object->cache;
        })->bindTo(null, DataObject::class)($object);
        self::assertFalse($cache->isCached('0/v'));
        self::assertFalse($cache->isCached('199/v'));
        self::assertFalse(property_exists(DataObject::class, 'paths'));
        self::assertSame('199/v', $object->getCurrentPath());
    }

    public function testRootReadsDoNotCopyLargeStorage(): void
    {
        $object = StringObjects::instance(array_fill(0, 100000, 1));
        $start = microtime(true);

        for ($index = 0; $index < 2000; $index++) {
            $object->get('5');
        }

        // Copying 100,000 entries per read took seconds; constant-time reads take milliseconds.
        self::assertLessThan(1.0, microtime(true) - $start);
    }

    public function testExplicitCacheEntriesNeverShadowCurrentData(): void
    {
        $object = new DataObject(['a' => 1]);
        $object->cache('a', 'stale');
        self::assertSame(1, $object->get('a'));
        $object->setOffset('a', 2);
        self::assertSame(2, $object->get('a'));
    }

    public function testRevisionTracksLibraryWritesAndInheritedMutations(): void
    {
        $object = new DataObject(['b' => 2, 'a' => 1]);
        self::assertSame(0, $object->getRevision());
        $object->set('c', 3);
        $object->asort();
        self::assertSame(1, $object->getRevision());
        $object->ksort();
        self::assertSame(1, $object->getRevision());
        $object->uksort(static function ($left, $right): int {
            return strcmp((string) $right, (string) $left);
        });
        self::assertSame(2, $object->getRevision());
        self::assertSame(2, $object->getRevision());
    }
}
