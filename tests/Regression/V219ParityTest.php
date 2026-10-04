<?php

declare(strict_types=1);

namespace StrObj\Tests\Regression;

use PHPUnit\Framework\TestCase;
use StrObj\Data\DataObject;
use StrObj\Helpers\PathResolver;
use StrObj\StringObjects;

/** Keeps legacy results that v2.1.9 applications already rely on. */
final class V219ParityTest extends TestCase
{
    /** @see https://github.com/uuur86/strobj/issues/24 */
    public function testNumericRootKeysResolveAfterTheLegacyObjectCast(): void
    {
        $list = StringObjects::instance([['a' => 1], ['a' => 2]]);
        self::assertSame(1, $list->get('0/a'));
        self::assertSame(2, $list->get('1/a'));
        self::assertTrue($list->has('0'));
        self::assertTrue($list->has('1/a'));
        self::assertFalse($list->has('2'));
        self::assertSame([1, 2], $list->get('*/a'));

        $keyed = StringObjects::instance(['0' => ['x' => 1], '7' => 'seven']);
        self::assertSame(1, $keyed->get('0/x'));
        self::assertSame('seven', $keyed->get('7'));
        $keyed->set('0/x', 2);
        $keyed->set('8', 'eight');
        self::assertSame(2, $keyed->get('0/x'));
        self::assertSame('eight', $keyed->get('8'));
    }

    /** @see https://github.com/uuur86/strobj/issues/24 */
    public function testNumericRootKeysResolveForObjectRootsInBothProfiles(): void
    {
        $json = '{"0":{"name":"Neo"},"10":true}';

        foreach ([StringObjects::instance($json), StringObjects::consistent($json)] as $object) {
            self::assertSame('Neo', $object->get('0/name'));
            self::assertTrue($object->get('10'));
            self::assertTrue($object->isValid('0/name'));
        }

        $data = new DataObject((object) [['a' => 1]]);
        self::assertSame(['0' => ['exists' => true, 'value' => ['a' => 1]]], $data->findMatches('0'));
        self::assertSame(['1/a' => ['exists' => false, 'value' => null]], $data->findMatches('1/a'));
    }

    /** @see https://github.com/uuur86/strobj/issues/26 */
    public function testLegacyToArrayKeepsNestedValuesUnchanged(): void
    {
        $legacy = StringObjects::instance('{"a":{"b":1},"list":[{"c":2}]}')->toArray();
        self::assertInstanceOf(\stdClass::class, $legacy['a']);
        self::assertSame(1, $legacy['a']->b);
        self::assertSame(2, $legacy['list'][0]->c);

        $consistent = StringObjects::consistent('{"a":{"b":1},"list":[{"c":2}]}')->toArray();
        self::assertSame(['a' => ['b' => 1], 'list' => [['c' => 2]]], $consistent);
    }

    /** @see https://github.com/uuur86/strobj/issues/26 */
    public function testLegacyToArrayAcceptsAnyStringAndNormalizesRootKeys(): void
    {
        self::assertSame(['a' => "\xB1"], StringObjects::instance(['a' => "\xB1"])->toArray());

        $list = StringObjects::instance([['a' => 1], ['a' => 2]])->toArray();
        self::assertSame([0, 1], array_keys($list));
        self::assertSame(['a' => 2], $list[1]);
    }

    /** @see https://github.com/uuur86/strobj/issues/24 */
    public function testRebuiltRootsMatchTheObjectCastOnEveryRuntime(): void
    {
        $entries = [['a' => 1], 'x' => 2, 5 => 3];
        $object = PathResolver::objectFromEntries($entries);
        self::assertEquals((object) $entries, $object);
        self::assertSame(json_encode((object) $entries), json_encode($object));
        self::assertTrue((new \ArrayObject($object))->offsetExists('5'));

        $legacy = StringObjects::instance('{"0":{"a":1},"b":2}');
        self::assertSame('{"0":{"a":1},"b":2}', $legacy->toJson());
        $legacy->set('0/a', 3);
        self::assertSame('{"0":{"a":3},"b":2}', $legacy->toJson());
    }
}
