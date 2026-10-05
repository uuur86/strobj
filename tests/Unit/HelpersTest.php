<?php

declare(strict_types=1);

namespace StrObj\Tests\Unit;

use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;
use StrObj\Data\DataPath;
use StrObj\Helpers\Adapters;
use StrObj\Helpers\DataParsers;

final class HelpersTest extends TestCase
{
    private function adapter(): object
    {
        return new class {
            use Adapters;
        };
    }

    private function parser(): object
    {
        return new class {
            use DataParsers;

            public function choose(string $path, array $options): string
            {
                return $this->findInclusivePaths($path, $options);
            }
        };
    }

    /** @dataProvider byteAmounts */
    public function testByteConversion($amount, int $expected): void
    {
        self::assertSame($expected, $this->adapter()->convertToByte($amount));
    }

    public function byteAmounts(): array
    {
        return [[0, 0], [12, 12], ['128', 128], ['-1', -1], [' 1 KB ', 1024],
            ['1k', 1024], ['1.5M', 1572864], ['2mb', 2097152], ['1G', 1073741824],
            ['1GB', 1073741824], ['1TB', 1099511627776], ['1b', 1]];
    }

    /** @dataProvider invalidAmounts */
    public function testInvalidAmountsAreRejected($amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->adapter()->convertToByte($amount);
    }

    public function invalidAmounts(): array
    {
        return [[null], [true], [1.5], [''], ['-2'], ['lots'], ['1XB'], ['999999999999999999999TB']];
    }

    /** @dataProvider formattedBytes */
    public function testFormattedBytes(int $value, string $expected): void
    {
        self::assertSame($expected, $this->adapter()->convertToString($value));
    }

    public function formattedBytes(): array
    {
        return [[0, '0 b'], [1, '1 b'], [1024, '1 kb'], [2097152, '2 mb'], [1073741824, '1 gb']];
    }

    public function testNegativeByteCountIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->adapter()->convertToString(-1);
    }

    /** @dataProvider scalarCasts */
    public function testScalarAndArrayCasts($value, string $type, $expected): void
    {
        self::assertSame($expected, $this->adapter()->castType($value, $type));
    }

    public function scalarCasts(): array
    {
        return [['12', 'int', 12], ['1.25', 'float', 1.25], [0, 'bool', false],
            ['false', 'bool', true], [12, 'string', '12'], [12, 'array', [12]]];
    }

    public function testObjectAndJsonCasts(): void
    {
        self::assertEquals((object) ['a' => 1], $this->adapter()->castType(['a' => 1], 'object'));
        self::assertEquals((object) ['a' => 1], $this->adapter()->castType('{"a":1}', 'json'));
    }

    public function testInvalidJsonCastReportsJsonError(): void
    {
        $this->expectException(JsonException::class);
        $this->adapter()->castTypeStrict('{', 'json');
    }

    public function testUnknownCastIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->adapter()->castTypeStrict('12', 'imaginary');
    }

    /** @dataProvider paths */
    public function testPathParsingAndNormalization(string $path, array $expected): void
    {
        $parsed = DataPath::init($path);
        self::assertSame($path, $parsed->getRaw());
        self::assertSame($expected, $parsed->getArray());
        self::assertSame(implode('/', $expected), $parsed->normalizePath($path));
    }

    public function paths(): array
    {
        return [['', []], ['///', []], ['0/age', ['0', 'age']],
            ['/users//0/name/', ['users', '0', 'name']], ['café/name', ['café', 'name']],
            ['groups/*/persons/*/age', ['groups', '*', 'persons', '*', 'age']]];
    }

    public function testBranchesAreStableAndIndependentOfPriorQueries(): void
    {
        $path = new DataPath('/a/b/');
        self::assertTrue($path->exists('a'));
        self::assertTrue($path->exists('/a/b/'));
        self::assertFalse($path->exists('a/c'));
        self::assertSame(['a', 'a/b'], $path->getBranches());
        self::assertSame(['a', 'a/b'], $path->getBranches());
        self::assertSame([], (new DataPath(''))->getBranches());
    }

    public function testOverlongPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DataPath(implode('/', array_fill(0, 513, 'a')));
    }

    /** @dataProvider pathMatches */
    public function testFullSegmentMatching(string $pattern, string $path, bool $expected): void
    {
        self::assertSame($expected, $this->parser()->matchesPath($pattern, $path));
    }

    public function pathMatches(): array
    {
        return [['*/age', 'user/age', true], ['persons/*', 'persons/100', true],
            ['a/*/name', 'a/very-long-key/name', true], ['a/*/name', 'a/1/age', false],
            ['a/*', 'a/1/age', false], ['', '', true], ['a', 'b', false]];
    }

    public function testPatternSelectionIsSpecificAndDoesNotMutateTheQuery(): void
    {
        $parser = $this->parser();
        $options = ['people/*/profile/name' => [], 'people/12/*/age' => [], '*/*/*/age' => []];
        self::assertSame('people/12/*/age', $parser->choose('people/12/profile/age', $options));
        self::assertSame('', $parser->choose('missing', $options));
        self::assertSame('people/12/profile/age', $parser->choose(
            'people/12/profile/age',
            $options + ['people/12/profile/age' => []]
        ));
        self::assertSame('0', $parser->choose('0', [0 => []]));
    }

    public function testProjectedPathExpansionPreservesKeysAndSupportsCallbacks(): void
    {
        $parser = $this->parser();
        self::assertSame(
            ['persons/u1/age' => 12, 'persons/5/age' => 21],
            $parser->findPaths('persons/*/age', ['u1' => 12, 5 => 21])
        );
        self::assertSame(['x' => [12]], $parser->findPaths('x', [12]));
        self::assertSame(['x' => 1], $parser->findPaths('x', [12], static function ($path, $data) {
            return count($data);
        }));
        self::assertSame(['g/u/age' => 13], $parser->findPaths(
            '*/*/age',
            ['g' => ['u' => 12]],
            static function ($path, $value) {
                return $value + 1;
            }
        ));
        self::assertSame([], $parser->findPaths('*/*/age', ['g' => 12]));
        self::assertSame([], $parser->findPaths('persons/*/age', []));
    }
}
