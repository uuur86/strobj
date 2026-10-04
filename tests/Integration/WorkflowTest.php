<?php

declare(strict_types=1);

namespace StrObj\Tests\Integration;

use PHPUnit\Framework\TestCase;
use StrObj\StringObjects;

final class WorkflowTest extends TestCase
{
    public function testFilteringValidationAndExportStayConsistentAfterWrites(): void
    {
        $object = StringObjects::consistent('{"persons":[{"age":"12","name":"John"}]}', [
            'validation' => ['patterns' => ['digits' => '#^[0-9]+$#'], 'rules' => [
                ['path' => 'persons/*/age', 'pattern' => 'digits', 'required' => true],
                ['path' => 'persons/*/name', 'pattern' => '#^[a-z]+$#i', 'required' => true],
            ]],
            'filters' => ['persons/*/age' => ['type' => 'int', 'callback' => static function ($value) {
                return $value >= 10;
            }]],
            'middleware' => ['memory_limit' => -1],
        ]);
        self::assertSame(12, $object->get('persons/0/age'));
        self::assertSame('12', $object->toArray()['persons'][0]['age']);
        self::assertTrue($object->isValid());
        $object->set('persons/1', ['age' => 'bad', 'name' => 'Neo']);
        self::assertFalse($object->isValid('persons/1/age'));
        self::assertFalse($object->isValid('persons'));
        self::assertFalse($object->isValid());
        self::assertSame([12, false], $object->get('persons/*/age'));
        $object->set('persons/1/age', '21');
        self::assertTrue($object->isValid());
        self::assertSame([12, 21], $object->get('persons/*/age'));
        $expected = ['persons' => [['age' => '12', 'name' => 'John'], ['age' => '21', 'name' => 'Neo']]];
        self::assertSame($expected, $object->toArray());
        self::assertSame($expected, json_decode($object->toJson(), true));
    }

    public function testTwoInstancesCannotShareDataCacheOrValidationState(): void
    {
        $input = (object) ['age' => '12'];
        $options = ['validation' => ['rules' => [
            ['path' => 'age', 'pattern' => '#^[0-9]+$#', 'required' => true],
        ]]];
        $first = StringObjects::consistent($input, $options);
        $second = StringObjects::consistent($input, $options);
        $first->get('age');
        $second->get('age');
        $first->set('age', 'bad');
        $input->age = 'also bad';
        self::assertFalse($first->isValid());
        self::assertTrue($second->isValid());
        self::assertSame('12', $second->get('age'));
        self::assertSame('{"age":"12"}', $second->toJson());
    }

    /** @dataProvider mutationSeeds */
    public function testRepeatedMutationsAgreeWithAnIndependentArrayModel(int $seed): void
    {
        $model = ['persons' => [], 'keep' => ['value' => 'unchanged']];
        $object = StringObjects::consistent($model);
        $state = $seed;
        $values = [null, false, 0, '0', '', 'Neo', ['nested' => 21]];

        for ($step = 0; $step < 60; $step++) {
            // A local deterministic generator avoids modifying PHP's global random state.
            $state = ($state * 1664525 + 1013904223) & 0x7fffffff;
            $index = $state % 5;
            $value = $values[($state >> 4) % count($values)];
            $path = 'persons/' . $index . '/value';
            $object->get($path, 'missing');
            $object->get('persons');
            $object->get('');
            $model['persons'][$index]['value'] = $value;
            $object->set('/persons//' . $index . '/value/', $value);
            self::assertSame($value, $object->get($path, 'missing'), 'seed ' . $seed . ', step ' . $step);
            self::assertTrue($object->has($path));
            self::assertSame($model, $object->toArray());
            self::assertSame($model, json_decode($object->toJson(), true));
            self::assertSame(array_column(array_values($model['persons']), 'value'), $object->get('persons/*/value'));
            self::assertFalse($object->has('persons/' . $index . '/absent'));
        }
    }

    public function mutationSeeds(): array
    {
        return [[1], [17], [2026]];
    }
}
