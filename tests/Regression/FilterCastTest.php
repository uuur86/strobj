<?php

declare(strict_types=1);

namespace StrObj\Tests\Regression;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use StrObj\Data\DataFilters;
use StrObj\StringObjects;

/**
 * Casts never raise PHP errors or warnings for values they cannot convert.
 *
 * @see https://github.com/uuur86/strobj/issues/27
 */
final class FilterCastTest extends TestCase
{
    /** @dataProvider impossibleCasts */
    public function testLegacyFiltersReturnValuesThatCannotBeCastUnchanged($value, string $type): void
    {
        self::assertSame($value, (new DataFilters(['field' => ['type' => $type]]))->filter('field', $value));
    }

    /** @dataProvider impossibleCasts */
    public function testConsistentFiltersRejectValuesThatCannotBeCast($value, string $type): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cast a value of type ');
        DataFilters::consistent(['field' => ['type' => $type]])->filterAt('field', $value);
    }

    public function impossibleCasts(): array
    {
        $object = new stdClass();

        return [
            'object to string' => [$object, 'string'],
            'array to string' => [[1], 'string'],
            'object to int' => [$object, 'int'],
            'object to float' => [$object, 'float'],
            'object to json' => [$object, 'json'],
            'array to json' => [[1], 'json'],
        ];
    }

    public function testLegacyJsonCastsDecodeScalarsLikeVersion219(): void
    {
        $filters = new DataFilters(['field' => ['type' => 'json']]);
        self::assertSame(5, $filters->filter('field', 5));
        self::assertSame(1.5, $filters->filter('field', 1.5));
        self::assertSame(1, $filters->filter('field', true));
        self::assertNull($filters->filter('field', null));
        self::assertEquals((object) ['a' => 1], $filters->filter('field', '{"a":1}'));
    }

    public function testConsistentJsonCastsRequireStrings(): void
    {
        $filters = DataFilters::consistent(['field' => ['type' => 'json']]);
        self::assertSame([1, 2], $filters->filterAt('field', '[1,2]'));
        self::assertNull($filters->filterAt('field', 'null'));
        $this->expectException(InvalidArgumentException::class);
        $filters->filterAt('field', 5);
    }

    public function testStringableObjectsAndScalarsStillCast(): void
    {
        $stringable = new class {
            public function __toString(): string
            {
                return 'text';
            }
        };
        $filters = DataFilters::consistent(['field' => ['type' => 'string']]);
        self::assertSame('text', $filters->filterAt('field', $stringable));
        self::assertSame('', $filters->filterAt('field', null));
        self::assertSame(0, DataFilters::consistent(['field' => ['type' => 'int']])->filterAt('field', []));
    }

    public function testCallbackOnlyFilterOnAnObjectNoLongerCrashesLegacyReads(): void
    {
        $accept = static function (): bool {
            return true;
        };
        $object = StringObjects::instance('{"a":{"b":1}}', ['filters' => ['a' => ['callback' => $accept]]]);
        self::assertEquals((object) ['b' => 1], $object->get('a'));
    }
}
