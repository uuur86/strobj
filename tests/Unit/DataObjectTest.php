<?php

declare(strict_types=1);

namespace StrObj\Tests\Unit;

use ArrayIterator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StrObj\Data\DataObject;

final class DataObjectTest extends TestCase
{
    /** @dataProvider inputContainers */
    public function testReadWriteSnapshotsAndCacheConsistency($input): void
    {
        $object = DataObject::snapshot($input);
        self::assertSame('', $object->getCurrentPath());
        self::assertSame(0, $object->getRevision());
        self::assertSame(1, $object->get('person/age'));
        self::assertSame(1, $object->get('/person//age/'));
        self::assertTrue($object->has('person/age'));
        self::assertNull($object->get('missing'));
        self::assertNull($object->get('missing'));
        self::assertNull($object->query('person/age/child'));
        $object->get('');
        $object->set('person/age', 2);
        self::assertSame(1, $object->getRevision());
        self::assertSame(2, $object->get('person/age'));
        self::assertSame(2, $object->toArray()['person']['age']);
        $object->set('new/0/name', 'Neo');
        self::assertSame('Neo', $object->query('new/0/name'));
        self::assertSame('new/0/name', $object->getCurrentPath());
        self::assertSame(['name' => 'Neo'], $object->toArray()['new'][0]);
    }

    public function inputContainers(): array
    {
        return [[['person' => ['age' => 1]]], [json_decode('{"person":{"age":1}}')],
            [new ArrayIterator(['person' => ['age' => 1]])]];
    }

    public function testScalarIntermediatesBecomeArraysAndRootZeroKeysWork(): void
    {
        $object = DataObject::snapshot(['a' => 1]);
        $object->set('a/b', 2);
        $object->set('0', 'zero');
        self::assertSame(['a' => ['b' => 2], 0 => 'zero'], $object->toArray());
    }

    public function testQueriesDoNotDependOnIteratorPosition(): void
    {
        $object = DataObject::snapshot(['a' => 1, 'b' => 2]);
        self::assertTrue($object->findKey('b'));
        self::assertSame('b', $object->key());
        self::assertSame(2, $object->current());
        self::assertSame(1, $object->query('a'));
        self::assertFalse($object->findKey('missing'));
        self::assertSame(1, $object->get('a'));
        self::assertTrue($object->findKey('a'));
    }

    public function testDirectIteratorMutationsInvalidateCachedReads(): void
    {
        $object = DataObject::snapshot(['a' => 1]);
        $object->get('a');
        $object['a'] = 2;
        self::assertSame(2, $object->get('a'));
        unset($object['a']);
        self::assertFalse($object->has('a'));
        self::assertNull($object->get('a'));
        $object->append(['name' => 'Neo']);
        self::assertSame([['name' => 'Neo']], $object->toArray());
    }

    public function testThrowingComparatorStillInvalidatesPreviouslyCachedData(): void
    {
        $object = DataObject::snapshot(['b' => 2, 'a' => 1]);
        $object->get('');

        try {
            $object->uasort(static function (): int {
                throw new \RuntimeException('comparator failed');
            });
            self::fail('Comparator exception should propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('comparator failed', $exception->getMessage());
            self::assertSame($object->toArray(), $object->get(''));
            self::assertSame(0, $object->getRevision());
        }
    }

    /** @dataProvider comparatorMethods */
    public function testInvalidComparatorIsRejectedWithoutMutation(string $method): void
    {
        $object = DataObject::snapshot(['b' => 2, 'a' => 1]);

        try {
            $object->{$method}('not_a_callable');
            self::fail('Invalid comparator should be rejected.');
        } catch (\Throwable $exception) {
            // PHP 8 throws TypeError; PHP 7.4 raises a warning that PHPUnit converts to an exception.
            self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $exception);
            self::assertSame(['b' => 2, 'a' => 1], $object->toArray());
            self::assertSame(0, $object->getRevision());
        }
    }

    public function comparatorMethods(): array
    {
        return [['uasort'], ['uksort']];
    }

    public function testSortFlagsAreForwardedToSpl(): void
    {
        $values = DataObject::snapshot(['b' => '10', 'a' => '2']);
        $values->asort(SORT_NUMERIC);
        self::assertSame(['a' => '2', 'b' => '10'], $values->toArray());
        $keys = DataObject::snapshot([10 => 'ten', 2 => 'two']);
        $keys->ksort(SORT_STRING);
        self::assertSame([10 => 'ten', 2 => 'two'], $keys->toArray());
        $keys->ksort(SORT_NUMERIC);
        self::assertSame([2 => 'two', 10 => 'ten'], $keys->toArray());
    }

    public function testReturnedObjectsAndInputObjectsAreDetached(): void
    {
        $input = (object) ['person' => (object) ['age' => 1]];
        $object = DataObject::snapshot($input);
        $input->person->age = 10;
        $first = $object->get('person');
        $first->age = 20;
        $cached = $object->get('person');
        $cached->age = 30;
        $offset = $object['person'];
        $offset->age = 40;
        $object->rewind();
        $current = $object->current();
        $current->age = 50;
        $copy = $object->getArrayCopy();
        $copy['person']->age = 60;
        self::assertSame(1, $object->get('person/age'));
        self::assertSame(['person' => ['age' => 1]], $object->toArray());
    }

    public function testWildcardProjectionAndMissingMatches(): void
    {
        $object = DataObject::snapshot(['persons' => ['u1' => ['age' => 12], 'u2' => [], 'u3' => ['age' => null]]]);
        self::assertSame([12, null, null], $object->get('persons/*/age'));
        self::assertSame([12, null, null], $object->query('persons/*/age'));
        self::assertTrue($object->has('persons/*/age'));
        self::assertFalse($object->has('persons/*/missing'));
        self::assertFalse($object->has('absent/*/age'));
        self::assertSame([], $object->query('persons/u1/age/*'));
        self::assertSame([
            'persons/u1/age' => ['exists' => true, 'value' => 12],
            'persons/u2/age' => ['exists' => false, 'value' => null],
            'persons/u3/age' => ['exists' => true, 'value' => null],
        ], $object->findMatches('persons/*/age'));
        self::assertSame([], $object->findMatches('persons/u1/age/*'));
        self::assertSame([['age' => 12], [], ['age' => null]], $object->query('persons/*'));
    }

    public function testRootAndColumnQueries(): void
    {
        $object = DataObject::snapshot([['age' => 12], ['name' => 'missing']]);
        self::assertSame([12, null], $object->getCols('age'));
        self::assertSame($object->toArray(), $object->query());
        self::assertSame($object->toArray(), $object->query('*'));
        self::assertTrue($object->has(''));
    }

    /** @dataProvider invalidWrites */
    public function testInvalidWritesAreAtomic(string $path): void
    {
        $object = DataObject::snapshot(['a' => 1]);

        try {
            $object->set($path, 2);
            self::fail('Invalid write was accepted.');
        } catch (InvalidArgumentException $error) {
            self::assertSame(['a' => 1], $object->toArray());
            self::assertSame(0, $object->getRevision());
        }
    }

    public function invalidWrites(): array
    {
        return [[''], ['/'], ['a/*'], ['*/b']];
    }

    public function testNonContainerInputIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DataObject::snapshot(1);
    }

    public function testCyclicWriteIsRejectedWithoutChangingData(): void
    {
        $object = DataObject::snapshot(['keep' => 1]);
        $cycle = new \stdClass();
        $cycle->self = $cycle;

        try {
            $object->set('cycle', $cycle);
            self::fail('Cyclic write was accepted.');
        } catch (InvalidArgumentException $error) {
            self::assertSame(['keep' => 1], $object->toArray());
            self::assertSame(0, $object->getRevision());
        }
    }
}
