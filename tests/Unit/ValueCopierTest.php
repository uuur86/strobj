<?php

declare(strict_types=1);

namespace StrObj\Tests\Unit;

use DateTimeImmutable;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use StrObj\Data\DataObject;
use StrObj\Helpers\ValueCopier;
use StrObj\Helpers\PathResolver;
use StrObj\StringObjects;
use StrObj\Tests\Fixtures\CopyableParent;
use StrObj\Tests\Fixtures\MutableCollection;

final class ValueCopierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../Fixtures/CopyableParent.php';
        require_once __DIR__ . '/../Fixtures/MutableCollection.php';
    }

    public function testCustomClassesPrivateInheritedStateAndSerializerSurviveCopying(): void
    {
        $input = new class extends CopyableParent implements JsonSerializable {
            private object $state;
            public object $publicState;

            public function __construct()
            {
                parent::__construct();
                $this->state = (object) ['value' => 3];
                $this->publicState = (object) ['value' => 4];
            }

            public function jsonSerialize(): array
            {
                return ['private' => $this->state->value, 'public' => $this->publicState->value];
            }

            public function change(): void
            {
                $this->state->value = 30;
                $this->publicState->value = 40;

                foreach ($this->states() as $state) {
                    $state->value = 10;
                }
            }
        };
        $object = StringObjects::consistent(['record' => $input]);
        $input->change();
        $result = $object->get('record');
        self::assertInstanceOf(get_class($input), $result);
        self::assertSame(1, $result->states()[0]->value);
        self::assertSame(2, $result->states()[1]->value);
        self::assertFalse((new \ReflectionProperty($result, 'uninitialized'))->isInitialized($result));
        self::assertSame(['record' => ['private' => 3, 'public' => 4]], $object->toArray());
        self::assertSame('{"record":{"private":3,"public":4}}', $object->toJson());
        $result->change();
        self::assertSame(['private' => 3, 'public' => 4], $object->get('record')->jsonSerialize());
    }

    public function testNativeObjectsRetainTheirMetadataAndMethods(): void
    {
        $date = new DateTimeImmutable('2023-01-01T00:00:00+00:00');

        $objects = [StringObjects::instance(['date' => $date]), StringObjects::consistent(['date' => $date])];

        foreach ($objects as $object) {
            self::assertInstanceOf(DateTimeImmutable::class, $object->get('date'));
            self::assertSame('2023-01-01', $object->get('date')->format('Y-m-d'));
            self::assertSame(json_decode(json_encode(['date' => $date]), true), $object->toArray());
            self::assertSame(json_encode(['date' => $date]), $object->toJson());
        }
    }

    public function testUncloneableHandlesRetainIdentity(): void
    {
        $handle = new class {
            private function __clone()
            {
            }
        };
        self::assertSame($handle, ValueCopier::copy($handle));
        $resource = fopen('php://memory', 'r+');

        try {
            self::assertSame($resource, ValueCopier::copy($resource));
        } finally {
            fclose($resource);
        }
    }

    public function testNativeCloneHookAndReadonlyStateAreRespected(): void
    {
        $value = new class {
            public int $clones = 0;
            public function __clone()
            {
                $this->clones++;
            }
        };
        self::assertSame(1, ValueCopier::copy($value)->clones);
        self::assertSame(0, $value->clones);

        if (PHP_VERSION_ID >= 80100) {
            $readonly = eval('return new class { public readonly object $state;'
                . 'public function __construct() { $this->state = (object) ["value" => 1]; } };');
            self::assertSame($readonly->state, ValueCopier::copy($readonly)->state);
        }
    }

    public function testPrivateCycleIsRejectedBeforeAWriteIsCommitted(): void
    {
        $cycle = new class {
            private object $self;
            public function __construct()
            {
                $this->self = $this;
            }
        };
        $object = DataObject::snapshot(['keep' => 1]);

        try {
            $object->setOffset('cycle', $cycle);
            self::fail('Private cyclic state must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(['keep' => 1], $object->toArray());
            self::assertSame(0, $object->getRevision());
        }
    }

    public function testExcessiveArrayDepthIsRejected(): void
    {
        $data = 1;

        for ($depth = 0; $depth < 514; $depth++) {
            $data = [$data];
        }

        $this->expectException(\InvalidArgumentException::class);
        ValueCopier::copy($data);
    }

    /** @dataProvider writableCollections */
    public function testNestedCollectionsPreserveClassPathsAndMutableEntryIsolation(string $class): void
    {
        $entry = (object) ['age' => 12];
        $collection = new $class(['first' => $entry, 'nil' => null]);
        $object = StringObjects::consistent(['records' => $collection]);
        self::assertSame($entry, $collection['first']);
        $entry->age = 21;
        self::assertInstanceOf($class, $object->get('records'));
        self::assertSame(12, $object->get('records/first/age'));
        self::assertNull($object->get('records/nil', 'fallback'));
        self::assertSame('fallback', $object->get('records/missing', 'fallback'));
        self::assertSame([12, null], $object->get('records/*/age'));
        $object->get('records')['first']->age = 30;
        self::assertSame(12, $object->get('records/first/age'));
        $object->set('records/first/age', 40);
        self::assertSame(40, $object->get('records/first/age'));
        self::assertSame(21, $entry->age);
        self::assertSame(21, $collection['first']->age);
        self::assertSame(['records' => ['first' => ['age' => 40], 'nil' => null]], $object->toArray());
    }

    public function writableCollections(): array
    {
        return [[\ArrayObject::class], [\ArrayIterator::class], [MutableCollection::class]];
    }

    public function testExistenceUsesPublicInitializedFieldsAndCollectionKeys(): void
    {
        $value = new class {
            public int $uninitialized;
            private int $hidden = 1;
            public $nil = null;
            public bool $flag = false;
        };
        self::assertTrue(PathResolver::contains($value, 'nil'));
        self::assertTrue(PathResolver::contains($value, 'flag'));
        self::assertFalse(PathResolver::contains($value, 'hidden'));
        self::assertFalse(PathResolver::contains($value, 'uninitialized'));
        self::assertTrue(PathResolver::contains(new \ArrayIterator(['nil' => null]), 'nil'));
        self::assertFalse(PathResolver::contains(12, 'value'));
    }

    public function testAsymmetricAndComputedPropertiesFollowTheirDeclaringScope(): void
    {
        if (PHP_VERSION_ID < 80400) {
            self::markTestSkipped('Asymmetric visibility and property hooks require PHP 8.4.');
        }

        $input = eval('return new class { public private(set) object $state;'
            . 'public int $age { get => $this->state->age; }'
            . 'public function __construct() { $this->state = (object) ["age" => 12]; } };');
        $copy = ValueCopier::copy($input);
        $input->state->age = 21;
        self::assertSame(12, $copy->state->age);
        self::assertSame(12, $copy->age);
    }

    public function testSnapshotWritesCannotChangeStoredPrivateCollectionStateBeforeCommit(): void
    {
        $collection = new class extends \ArrayObject {
            private object $storage;
            public function __construct()
            {
                $this->storage = (object) ['value' => 12];
                parent::__construct(['value' => 12]);
            }
            public function offsetSet($key, $value): void
            {
                $this->storage->value = $value;

                if ($value === 21) {
                    throw new \RuntimeException('Reject the candidate after invoking the setter.');
                }

                parent::offsetSet($key, $value);
            }
            public function state(): int
            {
                return $this->storage->value;
            }
        };
        $object = StringObjects::consistent(['record' => $collection]);

        try {
            $object->set('record/value', 21);
            self::fail('The setter must reject this write.');
        } catch (\RuntimeException $exception) {
            self::assertSame(12, $object->get('record')->state());
            self::assertSame(12, $object->get('record/value'));
            self::assertSame(12, $collection->state());
        }
    }

    public function testSnapshotRejectsAPathWriteIntoAnUncloneableContainer(): void
    {
        $container = new class {
            public int $value = 12;
            private function __clone()
            {
            }
        };
        $object = StringObjects::consistent(['record' => $container]);

        try {
            $object->set('record/value', 21);
            self::fail('Cannot detach an uncloneable container before a path write.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(12, $container->value);
            self::assertSame(12, $object->get('record/value'));
        }
    }

    public function testNonTraversableArrayAccessUsesItsExistenceContract(): void
    {
        $collection = new class implements \ArrayAccess, JsonSerializable {
            private array $entries = ['first' => 12, 'nil' => null];
            public function offsetExists($key): bool
            {
                return array_key_exists($key, $this->entries);
            }
            #[\ReturnTypeWillChange]
            public function offsetGet($key)
            {
                return $this->entries[$key];
            }
            public function offsetSet($key, $value): void
            {
                $this->entries[$key] = $value;
            }
            public function offsetUnset($key): void
            {
                unset($this->entries[$key]);
            }
            public function jsonSerialize(): array
            {
                return $this->entries;
            }
        };
        $object = StringObjects::consistent(['records' => $collection]);
        self::assertSame(12, $object->get('records/first'));
        self::assertNull($object->get('records/nil', 'fallback'));
        self::assertSame('fallback', $object->get('records/missing', 'fallback'));
        $object->set('records/first', 21);
        self::assertSame(12, $collection['first']);
        self::assertSame(21, $object->get('records/first'));
    }
}
