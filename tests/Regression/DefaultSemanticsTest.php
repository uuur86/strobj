<?php

declare(strict_types=1);

namespace StrObj\Tests\Regression;

use PHPUnit\Framework\TestCase;
use StrObj\Data\DataFilters;
use StrObj\StringObjects;

/**
 * Pins the documented contract for false, null, missing and rejected values.
 *
 * @see https://github.com/uuur86/strobj/issues/36
 */
final class DefaultSemanticsTest extends TestCase
{
    private const DATA = ['f' => false, 'n' => null, 'z' => 0, 'e' => '', 'a' => []];

    public function testConsistentReadsReturnStoredValuesAndUseTheDefaultOnlyWhenMissing(): void
    {
        $object = StringObjects::consistent(self::DATA);

        foreach (self::DATA as $path => $stored) {
            self::assertTrue($object->has($path));
            self::assertSame($stored, $object->get($path, 'default'));
        }

        self::assertFalse($object->has('missing'));
        self::assertSame('default', $object->get('missing', 'default'));
        self::assertSame('default', $object->get('f/below', 'default'));
        self::assertFalse($object->get('missing'));
    }

    public function testLegacyReadsKeepTheVersion219Contract(): void
    {
        $object = StringObjects::instance(self::DATA);
        self::assertSame('default', $object->get('f', 'default'));
        self::assertNull($object->get('n', 'default'));
        self::assertSame(0, $object->get('z', 'default'));
        self::assertNull($object->get('missing', 'default'));
        self::assertTrue($object->has('f'));
        self::assertFalse($object->has('missing'));
    }

    public function testConsistentFilterRejectionReturnsTheDefaultAndKeepsStoredFalse(): void
    {
        $data = ['age' => 5, 'flag' => false, 'list' => [['age' => 20], ['age' => 3], []]];
        $object = StringObjects::consistent($data, [
            'filters' => [
                'age' => ['type' => 'int', 'callback' => static function (int $value): bool {
                    return $value > 10;
                }],
                'flag' => ['type' => 'bool'],
                'list/*/age' => ['type' => 'int', 'callback' => static function (int $value): bool {
                    return $value > 10;
                }],
            ],
        ]);
        $rejected = new \stdClass();
        self::assertSame($rejected, $object->get('age', $rejected));
        self::assertFalse($object->get('age'));
        self::assertTrue($object->has('age'));
        self::assertFalse($object->get('flag', $rejected));
        self::assertSame([20, $rejected, null], $object->get('list/*/age', $rejected));
    }

    public function testLegacyFilterRejectionStillReturnsFalse(): void
    {
        $object = StringObjects::instance(['age' => 5], ['filters' => ['age' => [
            'type' => 'int',
            'callback' => static function (int $value): bool {
                return $value > 10;
            },
        ]]]);
        self::assertFalse($object->get('age', 'default'));
        self::assertSame('custom', DataFilters::consistent(['a' => ['callback' => 'is_numeric']])
            ->filterAt('a', 'x', 'custom'));
    }
}
