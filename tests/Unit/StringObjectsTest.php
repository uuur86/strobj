<?php

declare(strict_types=1);

namespace StrObj\Tests\Unit;

use Exception;
use InvalidArgumentException;
use JsonException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use StrObj\StringObjects;

final class StringObjectsTest extends TestCase
{
    /** @dataProvider inputs */
    public function testSupportedInputsAndRootShapes($input, string $json, array $expected): void
    {
        $object = StringObjects::instance($input);
        self::assertSame($json, $object->toJson());
        self::assertSame($expected, $object->toArray());
        self::assertTrue($object->has(''));
        self::assertTrue($object->isValid());
        self::assertEquals($object->get(''), $object->get(null));
    }

    public function inputs(): array
    {
        return [[[], '[]', []], [(object) [], '{}', []], ['[]', '[]', []], ['{}', '{}', []],
            ['[{"age":12}]', '[{"age":12}]', [['age' => 12]]],
            [new \ArrayIterator(['age' => 12]), '{"age":12}', ['age' => 12]]];
    }

    /** @dataProvider unsupportedInputs */
    public function testUnsupportedInputsHaveStableErrorCodes($input, int $code): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode($code);
        StringObjects::instance($input);
    }

    public function unsupportedInputs(): array
    {
        return [['{', 22], ['null', 23], ['12', 23], ['true', 23], ['"text"', 23],
            [12, 24], [null, 24], [false, 24]];
    }

    public function testMalformedJsonDoesNotDiscloseItsContents(): void
    {
        try {
            StringObjects::instance('{"secret":"do-not-print"');
            self::fail('Malformed JSON should be rejected.');
        } catch (Exception $exception) {
            self::assertStringNotContainsString('do-not-print', $exception->getMessage());
            self::assertSame(22, $exception->getCode());
        }
    }

    /** @dataProvider malformedOptions */
    public function testMalformedTopLevelOptionsAreRejected(array $options): void
    {
        $this->expectException(InvalidArgumentException::class);
        StringObjects::instance([], $options);
    }

    public function malformedOptions(): array
    {
        return [[['middleware' => 'bad']], [['validation' => false]], [['filters' => 12]]];
    }

    public function testFactoryPreservesSubclassType(): void
    {
        $subclass = new class ((object) []) extends StringObjects {
        };
        $copy = $subclass::instance(['age' => 12]);
        self::assertInstanceOf(get_class($subclass), $copy);
        self::assertSame(12, $copy->get('age'));
    }

    public function testUnencodableDataReportsJsonException(): void
    {
        $object = StringObjects::instance(['bad' => "\xB1\x31"]);
        $this->expectException(JsonException::class);
        $object->toJson();
    }

    public function testMemoryGuardCanBeChangedAfterConstruction(): void
    {
        $object = StringObjects::instance(['age' => 12]);
        $object->setMemoryLimit(1);
        $this->expectException(OverflowException::class);
        $object->get('age');
    }

    public function testConstructorChecksMemoryGuardBeforeCopyingData(): void
    {
        $this->expectException(OverflowException::class);
        StringObjects::instance(['age' => 12], ['middleware' => ['memory_limit' => 1]]);
    }
}
