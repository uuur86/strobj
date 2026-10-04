<?php

declare(strict_types=1);

namespace StrObj\Tests\Regression;

use Exception;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;
use StrObj\StringObjects;

/**
 * Facade failures are catchable exceptions with stable types and codes.
 *
 * @see https://github.com/uuur86/strobj/issues/29
 */
final class FacadeErrorTest extends TestCase
{
    /** @dataProvider unencodable */
    public function testToJsonThrowsJsonExceptionInBothProfiles($value): void
    {
        foreach ([StringObjects::instance(['a' => $value]), StringObjects::consistent(['a' => $value])] as $object) {
            try {
                $object->toJson();
                self::fail('Encoding should fail.');
            } catch (JsonException $exception) {
                self::assertNotSame(0, $exception->getCode());
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function unencodable(): array
    {
        return ['NAN' => [NAN], 'INF' => [INF], 'malformed UTF-8' => ["\xB1"]];
    }

    /** @dataProvider invalidInputs */
    public function testInvalidInputThrowsInvalidArgumentExceptionWithItsCode($input, int $code): void
    {
        try {
            StringObjects::instance($input);
            self::fail('Input should be rejected.');
        } catch (Exception $exception) {
            // Existing catch (Exception) blocks keep working.
            self::assertInstanceOf(InvalidArgumentException::class, $exception);
            self::assertSame($code, $exception->getCode());
        }
    }

    public function invalidInputs(): array
    {
        return [['{invalid', 22], ['"text"', 23], [5, 24], [null, 24]];
    }
}
