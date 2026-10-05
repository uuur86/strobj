<?php

declare(strict_types=1);

namespace StrObj\Tests\Unit;

use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;
use StrObj\Data\DataFilters;

final class FiltersTest extends TestCase
{
    /** @dataProvider casts */
    public function testConcreteFiltersApplyPhpCasts($value, string $type, $expected): void
    {
        $filters = DataFilters::consistent(['a' => ['type' => $type]]);
        $result = $filters->filterAt('a', $value);

        if (is_object($expected)) {
            self::assertInstanceOf(get_class($expected), $result);
            self::assertEquals($expected, $result);
        } else {
            self::assertSame($expected, $result);
        }
    }

    public function casts(): array
    {
        return [['12', 'int', 12], ['1.25', 'float', 1.25], [0, 'bool', false],
            [12, 'string', '12'], [12, 'array', [12]], [['a' => 1], 'object', (object) ['a' => 1]],
            ['{"a":1}', 'json', (object) ['a' => 1]]];
    }

    public function testUnmatchedAndEmptyFiltersLeaveValuesUnchanged(): void
    {
        $filters = DataFilters::consistent(['a/*/age' => ['type' => 'int']]);
        self::assertSame('12', $filters->filterAt('a/12/name', '12'));
        self::assertSame(['age' => '12'], $filters->filter('other', ['age' => '12']));
        self::assertSame('12', (DataFilters::consistent([]))->filterAt('a', '12'));
        self::assertSame('12', (DataFilters::consistent(['' => ['type' => 'int']]))->filterAt('a', '12'));
    }

    public function testExactAndMoreSpecificPatternsWinWithStableTies(): void
    {
        $filters = DataFilters::consistent([
            '*/*/age' => ['type' => 'string'], 'persons/*/age' => ['type' => 'float'],
            'persons/u1/age' => ['type' => 'int'],
        ]);
        self::assertSame(12, $filters->filterAt('persons/u1/age', '12'));
        self::assertSame(12.0, $filters->filterAt('persons/u2/age', '12'));
        self::assertSame('12', $filters->filterAt('other/u2/age', 12));
        $ties = DataFilters::consistent(['a/*' => ['type' => 'int'], '*/b' => ['type' => 'float']]);
        self::assertSame(12, $ties->filterAt('a/b', '12'));
    }

    public function testTreeFilteringPreservesObjectShapeAndDoesNotChangeInput(): void
    {
        $data = (object) ['persons' => [(object) ['age' => '12', 'name' => 'John']], 'other' => '12'];
        $filters = DataFilters::consistent(['persons/*/age' => ['type' => 'int']]);
        $result = $filters->filter('persons/0/age', $data);
        self::assertSame(12, $result->persons[0]->age);
        self::assertSame('12', $data->persons[0]->age);
        self::assertSame('John', $result->persons[0]->name);
        self::assertSame('12', $result->other);
        self::assertSame(12, $filters->filter('persons/u1/age', '12'));
    }

    public function testDefaultCastAndAllCallableFormsActAsPredicates(): void
    {
        $predicate = new class {
            public function __invoke($value, $min): bool
            {
                return $value >= $min;
            }
            public function accepts($value): bool
            {
                return $value === '12';
            }
        };
        $filters = DataFilters::consistent([
            'string' => ['callback' => 'is_string'],
            'method' => ['callback' => [$predicate, 'accepts']],
            'object' => ['type' => 'int', 'callback' => $predicate, 'args' => 10],
            'arrayArgs' => ['type' => 'int', 'callback' => $predicate, 'args' => ['minimum' => 20]],
        ]);
        self::assertSame('12', $filters->filterAt('string', 12));
        self::assertSame('12', $filters->filterAt('method', 12));
        self::assertSame(12, $filters->filterAt('object', '12'));
        self::assertFalse($filters->filterAt('arrayArgs', '12'));
    }

    /** @dataProvider malformedFilters */
    public function testMalformedOptionsAreRejected($options): void
    {
        $this->expectException(InvalidArgumentException::class);
        DataFilters::consistent(['a' => $options]);
    }

    public function malformedFilters(): array
    {
        return [[null], ['string'], [['type' => []]], [['callback' => 'no_such_callback']]];
    }

    public function testUnknownCastTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (DataFilters::consistent(['a' => ['type' => 'unknown']]))->filterAt('a', '12');
    }

    public function testInvalidJsonCastThrows(): void
    {
        $this->expectException(JsonException::class);
        (DataFilters::consistent(['a' => ['type' => 'json']]))->filterAt('a', '{');
    }

    public function testTreeFilteringUsesWritableCollectionEntries(): void
    {
        $input = ['records' => new \ArrayIterator(['first' => ['age' => '12']])];
        $filters = DataFilters::consistent(['records/*/age' => ['type' => 'int']]);
        $result = $filters->filter('records/first/age', $input);
        self::assertInstanceOf(\ArrayIterator::class, $result['records']);
        self::assertSame(12, $result['records']['first']['age']);
        self::assertSame('12', $input['records']['first']['age']);
    }
}
