<?php

declare(strict_types=1);

namespace StrObj\Tests\Regression;

use PHPUnit\Framework\TestCase;
use StrObj\Data\DataObject;
use StrObj\StringObjects;

/** Guards against lost SPL child writes and the former root-key write-back error. */
final class Gh10519RegressionTest extends TestCase
{
    /**
     * A deep write must preserve the whole document, including similarly named keys.
     *
     * @dataProvider deepWriteInputs
     * @see https://github.com/php/php-src/issues/10519
     */
    public function testDeepWriteNeverPromotesAChildKeyToTheRoot($input, string $path, array $expected): void
    {
        $object = StringObjects::instance($input);
        $object->get('');
        $object->get(explode('/', $path)[0]);
        $object->set($path, 5);

        self::assertSame(5, $object->get($path));
        // Legacy toArray() keeps nested objects, so compare the exported structure.
        self::assertSame($expected, json_decode(json_encode($object->toArray()), true));
        self::assertSame(json_encode($expected), $object->toJson());
    }

    /**
     * The underlying data object must invalidate cached ancestors after the write.
     *
     * @dataProvider deepWriteInputs
     */
    public function testDeepWriteRefreshesCachedAncestors($input, string $path, array $expected): void
    {
        $data = is_string($input) ? json_decode($input) : $input;
        $object = new DataObject($data);
        $rootKey = explode('/', $path)[0];
        $object->get('');
        $object->get($rootKey);
        $object->set($path, 5);

        self::assertSame($expected, json_decode(json_encode($object->toArray()), true));
        self::assertSame($expected, json_decode(json_encode($object->get('')), true));
        self::assertSame($expected[$rootKey], json_decode(json_encode($object->get($rootKey)), true));
        self::assertSame(1, $object->getRevision());
    }

    /** Covers array, object, JSON, mixed containers, root collisions and list indexes. */
    public function deepWriteInputs(): array
    {
        $input = ['a' => ['b' => ['c' => 1]]];
        $expected = ['a' => ['b' => ['c' => 1, 'd' => ['e' => 5]]]];
        $json = '{"a":{"b":{"c":1}}}';

        return [
            'array containers' => [$input, 'a/b/d/e', $expected],
            'object containers' => [json_decode($json), 'a/b/d/e', $expected],
            'JSON input' => [$json, 'a/b/d/e', $expected],
            'mixed containers' => [['a' => (object) ['b' => ['c' => 1]]], 'a/b/d/e', $expected],
            'existing root b stays intact' => [
                $input + ['b' => ['keep' => 7]],
                'a/b/d/e',
                $expected + ['b' => ['keep' => 7]],
            ],
            'numeric intermediate and sibling row' => [
                ['items' => [['b' => ['c' => 1]], ['b' => ['c' => 2]]]],
                'items/0/b/d/e',
                ['items' => [['b' => ['c' => 1, 'd' => ['e' => 5]]], ['b' => ['c' => 2]]]],
            ],
        ];
    }
}
