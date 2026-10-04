<?php

declare(strict_types=1);

namespace StrObj\Tests\Regression;

use PHPUnit\Framework\TestCase;
use StrObj\Data\DataObject;
use StrObj\StringObjects;

final class ConsistencyTest extends TestCase
{
    public function testReturnedPathCannotAlterLaterQueries(): void
    {
        $object = DataObject::snapshot(['a' => 1, 'b' => 2]);
        $path = $object->pathInit('a');
        $path[0] = 'b';
        self::assertSame(1, $object->query('a'));
        self::assertSame(1, $object->get('a'));
    }

    /** @dataProvider iteratorSorts */
    public function testIteratorSortingInvalidatesRootCache(string $method, array $input, array $expected): void
    {
        $object = DataObject::snapshot($input);
        $object->get('');
        $arguments = in_array($method, ['uasort', 'uksort'], true)
        ? [static function ($left, $right): int {
            return $left <=> $right;
        }] : [];
        $native = new \RecursiveArrayIterator($input);
        self::assertSame($native->{$method}(...$arguments), $object->{$method}(...$arguments));
        self::assertSame($expected, $object->toArray());
        self::assertSame($expected, $object->get(''));
        self::assertSame($expected, json_decode(json_encode($object), true));
        self::assertSame(1, $object->getRevision());
    }

    public function iteratorSorts(): array
    {
        return [['asort', ['b' => 2, 'a' => 1], ['a' => 1, 'b' => 2]],
            ['ksort', ['b' => 1, 'a' => 2], ['a' => 2, 'b' => 1]],
            ['natsort', ['b' => 'Item10', 'a' => 'Item2'], ['a' => 'Item2', 'b' => 'Item10']],
            ['natcasesort', ['b' => 'item10', 'a' => 'Item2'], ['a' => 'Item2', 'b' => 'item10']],
            ['uasort', ['b' => 2, 'a' => 1], ['a' => 1, 'b' => 2]],
            ['uksort', ['b' => 1, 'a' => 2], ['a' => 2, 'b' => 1]]];
    }

    public function testCloningDataObjectCannotMutateTheOriginal(): void
    {
        $original = DataObject::snapshot((object) ['person' => (object) ['age' => 12]]);
        $original->get('person/age');
        $copy = clone $original;
        $copy->set('person/age', 21);
        self::assertSame(12, $original->query('person/age'));
        self::assertSame(12, $original->get('person/age'));
        self::assertSame(21, $copy->get('person/age'));
        self::assertSame(0, $original->getRevision());
        self::assertSame(1, $copy->getRevision());
    }

    public function testExistingAndNullFieldsAreFoundWithoutPriorQueries(): void
    {
        $object = StringObjects::instance(['age' => 12, 'nil' => null, 'flag' => false]);
        self::assertTrue($object->has('age'));
        self::assertTrue($object->has('nil'));
        self::assertFalse($object->has('missing'));
        self::assertSame('fallback', $object->get('missing', 'fallback'));
        self::assertFalse($object->get('flag', 'fallback'));
        self::assertNull($object->get('nil', 'fallback'));
    }

    public function testContainerReplacementAgreesWithExportAndRead(): void
    {
        $object = StringObjects::instance(['x' => ['a' => 1], 'keep' => 7]);
        $object->get('x/a');
        $object->get('');
        $object->set('x', ['a' => 2]);
        self::assertSame(['x' => ['a' => 2], 'keep' => 7], $object->toArray());
        self::assertSame(2, $object->get('x/a'));
        self::assertSame(['a' => 2], $object->get('x'));
        self::assertSame($object->toArray(), json_decode($object->toJson(), true));
    }

    public function testNewNestedAndZeroIndexedPathsDoNotCreateExtraFields(): void
    {
        $object = StringObjects::instance([]);
        $object->set('items/0/name', 'Neo');
        $object->set('items/0/age', 21);
        self::assertSame(['items' => [['name' => 'Neo', 'age' => 21]]], $object->toArray());
    }

    public function testEquivalentCachedPathsStayFreshAfterMutation(): void
    {
        $object = StringObjects::instance(['age' => 1]);
        self::assertSame(1, $object->get('/age/'));
        $object->set('age', 2);
        self::assertSame(2, $object->get('/age/'));
    }

    public function testSparseWildcardValidationKeepsRealRecordPaths(): void
    {
        $object = StringObjects::instance([
            'persons' => ['first' => ['age' => 12], 'missing' => [], 'invalid' => ['age' => 'bad']],
        ], ['validation' => ['rules' => [
            ['path' => 'persons/*/age', 'pattern' => '#^[0-9]+$#', 'required' => true],
        ]]]);
        self::assertTrue($object->isValid('persons/first/age'));
        self::assertFalse($object->isValid('persons/missing/age'));
        self::assertFalse($object->isValid('persons/invalid/age'));
        self::assertFalse($object->isValid());
    }

    public function testValidationReflectsMutationsAndCombinesAllRules(): void
    {
        $object = StringObjects::instance(['person' => ['age' => '12', 'name' => 'John']], [
            'validation' => ['rules' => [
                ['path' => 'person/age', 'pattern' => '#^[0-9]+$#', 'required' => true],
                ['path' => 'person/name', 'pattern' => '#^[a-z]+$#i', 'required' => true],
            ]],
        ]);
        self::assertTrue($object->isValid());
        $object->set('person/name', '123');
        self::assertFalse($object->isValid('person/name'));
        self::assertFalse($object->isValid('person'));
        self::assertFalse($object->isValid());
        $object->set('person/name', 'Molly');
        self::assertTrue($object->isValid());
    }

    public function testIncrementalListWritesKeepJsonArrayShape(): void
    {
        $object = StringObjects::instance('{"persons":[{"name":"John"}]}');
        $object->set('persons/1/name', 'Neo');
        self::assertSame('{"persons":[{"name":"John"},{"name":"Neo"}]}', $object->toJson());
    }

    public function testNestedWildcardsAndOutputFiltersUseFullPaths(): void
    {
        $object = StringObjects::instance([
            'groups' => [['persons' => [['age' => '12'], ['age' => '21']]]],
        ], ['filters' => ['groups/*/persons/*/age' => ['type' => 'int']]]);
        self::assertSame([[12, 21]], $object->get('groups/*/persons/*/age'));
    }
}
