<?php

declare(strict_types=1);

namespace StrObj\Tests\Regression;

use PHPUnit\Framework\TestCase;
use StrObj\Data\DataObject;
use StrObj\StringObjects;

/**
 * Snapshots store the entries of object roots as arrays.
 * PHP 8.5 deprecates objects as ArrayObject and ArrayIterator storage, and object storage
 * also exposed non-public state and let appended values replace numeric properties.
 */
final class ObjectRootStorageTest extends TestCase
{
    public function testConsistentBehaviorRaisesNoDeprecations(): void
    {
        $deprecations = [];
        // User error handlers run whatever the error_reporting setting is.
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = ($level === E_USER_DEPRECATED ? 'E_USER_DEPRECATED: ' : 'E_DEPRECATED: ') . $message;

            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            $this->useTheConsistentBehavior();
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }

    public function testSnapshotsStoreOnlyThePublicEntriesOfObjectRoots(): void
    {
        $root = new class () {
            public int $visible = 1;
            protected int $internal = 2;
            private int $secret = 3;

            public function hidden(): int
            {
                return $this->internal + $this->secret;
            }
        };
        $object = StringObjects::instance($root);
        self::assertSame(['visible' => 1], $object->toArray());
        self::assertSame(['visible' => 1], $object->get(''));
        self::assertSame('{"visible":1}', $object->toJson());
        self::assertFalse($object->has('internal'));
        self::assertSame(5, $root->hidden());
    }

    public function testAppendingToAnObjectRootKeepsNumericEntries(): void
    {
        $root = new \stdClass();
        $root->{'0'} = 'first';
        $object = DataObject::snapshot($root);
        $object->append('second');
        $object[] = 'third';
        self::assertEquals((object) ['first', 'second', 'third'], $object->toJsonValue());
        self::assertSame('first', $root->{'0'});
    }

    public function testSnapshotChildrenKeepTheSplFlags(): void
    {
        $object = DataObject::snapshot(['record' => (object) ['age' => 12]]);
        $object->setFlags(\ArrayIterator::ARRAY_AS_PROPS);
        $object->rewind();
        $child = $object->getChildren();
        self::assertSame(\ArrayIterator::ARRAY_AS_PROPS, $child->getFlags());
        self::assertEquals((object) ['age' => 12], $child->toJsonValue());

        $object->setFlags(\RecursiveArrayIterator::CHILD_ARRAYS_ONLY);
        self::assertFalse($object->hasChildren());
        self::assertNull($object->getChildren());
    }

    /** Exercises every consistent entry point that creates or copies SPL storage. */
    private function useTheConsistentBehavior(): void
    {
        $document = '{"0":{"name":"Ada"},"profile":{"tags":["a","b"]},"meta":{"empty":{}}}';
        $inputs = [
            $document,
            json_decode($document),
            json_decode($document, true),
            (object) ['5' => (object) ['a' => 1]],
        ];

        foreach ($inputs as $input) {
            $object = StringObjects::instance($input, [
                'validation' => ['rules' => [['path' => '*/name', 'pattern' => '#^[A-Z]#']]],
                'filters' => ['profile/tags/*' => ['type' => 'string']],
            ]);
            $object->set('profile/address/city', 'Ankara');
            $object->get('*/name');
            $object->get('profile/tags/*');
            $object->has('0/name');
            $object->isValid();
            $object->toArray();
            $object->toJson();
        }

        $data = DataObject::snapshot((object) ['record' => (object) ['value' => (object) ['age' => 12]]]);
        $copy = clone $data;
        $copy->rewind();
        $copy->getChildren()->getChildren();
        $copy->setOffset('extra', (object) ['a' => 1]);
        $copy->toJsonValue();
    }
}
