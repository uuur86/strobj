<?php

declare(strict_types=1);

namespace StrObj\Tests\Regression;

use Error;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use StrObj\StringObjects;
use StrObj\Tests\Fixtures\Legacy;

/**
 * Object writes report rejected fields as invalid paths instead of PHP errors.
 *
 * @see https://github.com/uuur86/strobj/issues/34
 */
final class ObjectWriteTest extends TestCase
{
    /** @dataProvider rejectedWrites */
    public function testRejectedObjectWritesThrowInvalidArgumentException(callable $data, string $path, $value): void
    {
        foreach ([StringObjects::instance(['record' => $data()]), Legacy::of(['record' => $data()])] as $object) {
            try {
                $object->set($path, $value);
                self::fail('The write should be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertInstanceOf(Error::class, $exception->getPrevious());
                self::assertStringStartsWith('Cannot write the "', $exception->getMessage());
            }
        }
    }

    public function rejectedWrites(): array
    {
        return [
            'NUL byte' => [static function (): stdClass {
                return new stdClass();
            }, "record/\0name", 1],
            'private property' => [static function (): object {
                return new class {
                    private $role = 'user';
                };
            }, 'record/role', 'admin'],
            'typed property' => [static function (): object {
                return new class {
                    public int $count = 0;
                };
            }, 'record/count', 'many'],
        ];
    }

    public function testMagicSettersAndPublicPropertiesStillReceiveWrites(): void
    {
        $record = new class {
            public $name = 'old';
            public $received = [];

            public function __set(string $name, $value): void
            {
                $this->received[$name] = $value;
            }
        };
        $object = Legacy::of(['record' => $record]);
        $object->set('record/name', 'new');
        $object->set('record/hidden', 1);
        self::assertSame('new', $object->get('record/name'));
        self::assertSame(['hidden' => 1], $object->get('record/received'));
    }

    public function testUncloneableLegacyContainersAreRejected(): void
    {
        $handle = new class {
            public $value = 1;

            private function __clone()
            {
                // A private __clone() makes this object uncloneable on purpose.
            }
        };
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path writes require cloneable object containers.');
        Legacy::of(['handle' => $handle])->set('handle/value', 2);
    }
}
