<?php

declare(strict_types=1);

namespace StrObj\Tests\Regression;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RecursiveArrayIterator;
use StrObj\Behavior;
use StrObj\Data\DataFilters;
use StrObj\Data\DataObject;
use StrObj\Data\Validation;
use StrObj\Middleware;
use StrObj\StringObjects;
use StrObj\Tests\Fixtures\Legacy;

/** Preserves consumer contracts recorded from the v2.1 source before this change. */
final class CompatibilityTest extends TestCase
{
    /** Internal helpers removed from the facade in 3.0; every other v2.1 method is kept. */
    private const REMOVED_IN_3_0 = [
        StringObjects::class => ['convertToByte', 'convertToString', 'castType'],
    ];

    public function testInternalHelpersAreNoLongerPartOfTheFacade(): void
    {
        foreach (self::REMOVED_IN_3_0 as $class => $methods) {
            foreach ($methods as $method) {
                self::assertFalse(method_exists($class, $method), $class . '::' . $method);
            }
        }
    }

    public function testPublishedApiSignaturesAndConsumerOverridesRemainCompatible(): void
    {
        $contract = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/api-v2.1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($contract as $class => $methods) {
            $overrides = '';

            foreach (array_diff_key($methods, array_flip(self::REMOVED_IN_3_0[$class] ?? [])) as $name => $expected) {
                $method = new ReflectionMethod($class, $name);
                $label = $class . '::' . $name;
                self::assertNotFalse($method->getDocComment(), $label . ' documentation must be retained.');
                self::assertSame($expected['static'], $method->isStatic(), $label);
                self::assertSame($expected['public'], $method->isPublic(), $label);
                self::assertSame($expected['return'], $this->typeName($method->getReturnType()), $label);
                $parameters = $method->getParameters();
                self::assertGreaterThanOrEqual(count($expected['parameters']), count($parameters), $label);
                $signature = [];

                foreach ($expected['parameters'] as $index => $parameter) {
                    $actual = $parameters[$index];
                    self::assertSame($parameter['name'], $actual->getName(), $label);
                    self::assertSame($parameter['type'], $this->typeName($actual->getType()), $label);
                    self::assertSame($parameter['reference'], $actual->isPassedByReference(), $label);
                    self::assertSame($parameter['optional'], $actual->isOptional(), $label);

                    if ($parameter['optional']) {
                        self::assertSame($parameter['default'], $actual->getDefaultValue(), $label);
                    }

                    $signature[] = ($parameter['type'] === '' ? '' : $this->signatureType($actual->getType()) . ' ')
                    . ($parameter['reference'] ? '&' : '') . '$' . $parameter['name']
                    . ($parameter['optional'] ? ' = ' . var_export($parameter['default'], true) : '');
                }

                foreach (array_slice($parameters, count($expected['parameters'])) as $parameter) {
                    self::assertTrue($parameter->isOptional(), $label);
                }

                $overrides .= ($expected['public'] ? 'public ' : 'protected ')
                . ($expected['static'] ? 'static ' : '') . 'function ' . $name
                . '(' . implode(', ', $signature) . ')'
                . ($expected['return'] === '' ? '' : ': ' . $this->signatureType($method->getReturnType()))
                . ' { throw new \\LogicException("Consumer override fixture"); }';
            }

            // PHP must load real subclasses using the recorded contracts without a fatal error.
            $consumerName = __NAMESPACE__ . '\\ConsumerContract' . str_replace('\\', '', $class);

            if (!class_exists($consumerName, false)) {
                eval('namespace ' . __NAMESPACE__ . '; class ConsumerContract' . str_replace('\\', '', $class)
                    . ' extends \\' . $class . ' { ' . $overrides . ' }');
            }

            self::assertInstanceOf(ReflectionClass::class, new ReflectionClass($consumerName));
        }
    }

    /** Formats a type like PHP 8; PHP 7.4 omits the nullable marker when casting to string. */
    private function typeName(?\ReflectionType $type): string
    {
        if (!$type instanceof \ReflectionNamedType) {
            return (string) $type;
        }

        $nullable = $type->allowsNull() && !in_array($type->getName(), ['mixed', 'null'], true);

        return ($nullable ? '?' : '') . $type->getName();
    }

    private function signatureType(?\ReflectionNamedType $type): string
    {
        if ($type === null || $type->isBuiltin()) {
            return $this->typeName($type);
        }

        return ($type->allowsNull() ? '?' : '') . '\\' . $type->getName();
    }

    public function testInheritedSplOverridesKeepNativeParameterAndReturnContracts(): void
    {
        foreach (
            ['offsetGet', 'offsetUnset', 'append', 'current', 'getArrayCopy', 'getChildren',
            'asort', 'ksort', 'natsort', 'natcasesort', 'uasort', 'uksort'] as $name
        ) {
            $native = new ReflectionMethod(RecursiveArrayIterator::class, $name);
            $current = new ReflectionMethod(DataObject::class, $name);
            self::assertSame((string) $native->getReturnType(), (string) $current->getReturnType(), $name);

            foreach ($native->getParameters() as $index => $parameter) {
                $actual = $current->getParameters()[$index];
                self::assertSame(
                    str_replace('mixed', '', (string) $parameter->getType()),
                    str_replace('mixed', '', (string) $actual->getType()),
                    $name
                );

                if (PHP_VERSION_ID >= 80000) {
                    self::assertSame($parameter->getName(), $actual->getName(), $name);
                }
            }
        }
    }

    public function testOffsetSetSupportsOldNamedArgumentsAndVirtualDispatch(): void
    {
        $object = new class (['a' => 1]) extends DataObject {
            public int $writes = 0;
            public function offsetSet($index, $val): void
            {
                $this->writes++;
                parent::offsetSet($index, $val);
            }
        };

        if (PHP_VERSION_ID >= 80000) {
            eval('$object->offsetSet(index: "a", val: 2);');
        } else {
            $object->offsetSet('a', 2);
        }

        $object->set('nested/value', 3);
        $object->append(4);
        $object['b'] = 5;
        self::assertSame(4, $object->writes);
        $object->setOffset('a', 6);
        self::assertSame(6, $object->get('a'));
        self::assertSame(5, $object->getRevision());
        self::assertStringContainsString(
            '@deprecated',
            (new ReflectionMethod(DataObject::class, 'offsetSet'))->getDocComment()
        );
    }

    public function testLegacyAndConsistentDefaultsAreExplicit(): void
    {
        $input = ['false' => false, 'nil' => null];
        $legacy = Legacy::of($input);
        $consistent = StringObjects::instance($input);
        self::assertSame('fallback', $legacy->get('false', 'fallback'));
        self::assertNull($legacy->get('missing', 'fallback'));
        self::assertFalse($consistent->get('false', 'fallback'));
        self::assertSame('fallback', $consistent->get('missing', 'fallback'));

        foreach ([$legacy, $consistent] as $object) {
            self::assertNull($object->get('nil', 'fallback'));
            self::assertTrue($object->has('false'));
            self::assertTrue($object->has('nil'));
        }

        self::assertSame('[]', Legacy::of((object) [])->toJson());
        self::assertSame('{}', StringObjects::instance((object) [])->toJson());
        self::assertSame([], (new DataObject((object) []))->jsonSerialize());
        $casts = new DataFilters([]);
        self::assertSame('12', $casts->castType('12', 'integer'));
        self::assertNull($casts->castType('{', 'json'));
        self::assertSame(12, $casts->castTypeStrict('12', 'int'));
    }

    public function testLegacyFactoryAndConstructorKeepTheirSubclassContracts(): void
    {
        $consumer = new class ((object) []) extends StringObjects {
            public function __construct(object $obj, array $options = [])
            {
                parent::__construct($obj, $options);
            }
        };
        $legacy = ['behavior' => Behavior::LEGACY];
        self::assertSame(StringObjects::class, get_class($consumer::instance(['age' => 12], $legacy)));
        self::assertInstanceOf(get_class($consumer), $consumer::instance(['age' => 12]));
    }

    public function testLegacyObjectIdentityAndStrictSnapshotsAreSeparate(): void
    {
        $person = (object) ['age' => 12];
        $legacy = Legacy::of(['person' => $person]);
        $consistent = StringObjects::instance(['person' => $person]);
        self::assertSame($person, $legacy->get('person'));
        $person->age = 21;
        self::assertSame(21, $legacy->get('person')->age);
        self::assertSame(12, $consistent->get('person')->age);
        $data = new DataObject(['person' => $person]);
        $clone = clone $data;
        self::assertSame($person, $clone->get('person'));
    }

    /** @dataProvider scalarFlags */
    public function testLegacyRequiredFlagsAcceptScalarCoercion($flag, bool $required): void
    {
        $validation = new Validation(new DataObject(['value' => null]), ['rules' => [
            ['path' => 'value', 'pattern' => '#.*#', 'required' => $flag],
        ]]);
        self::assertSame(!$required, $validation->isValid());
    }

    public function scalarFlags(): array
    {
        return [['1', true], ['0', false], ['', false], [1, true], [0, false], [1.5, true]];
    }

    public function testLegacyPredicatesAndNumericMemoryLimitsStayCompatible(): void
    {
        $filters = new DataFilters([
            'string' => ['type' => 'integer', 'callback' => 'is_int'],
            'closure' => ['type' => 'int', 'callback' => static function ($value): bool {
                return $value > 20;
            }],
        ]);
        self::assertSame('12', $filters->filterAt('string', '12'));
        self::assertFalse($filters->filterAt('closure', '12'));
        self::assertSame('12', Legacy::of(['string' => '12'], [
            'filters' => ['string' => ['type' => 'integer']],
        ])->get('string'));

        foreach (['104857600', ' +00104857600 ', '1e8', 104857600.5, '-1', '-01', -1.0] as $value) {
            $middleware = new Middleware(['memory_limit' => $value]);
            self::assertSame($value, $middleware->get('memory_limit'));
            $middleware->memoryLeakProtection();
        }
    }

    public function testUnknownBehaviorProfileIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Legacy::of([], ['behavior' => 'unknown']);
    }

    public function testInheritedUnserializeMutationRefreshesCacheAndValidation(): void
    {
        $object = DataObject::snapshot(['value' => '12']);
        $validation = Validation::consistent($object, ['rules' => [
            ['path' => 'value', 'pattern' => '#^[0-9]+$#', 'required' => true],
        ]]);
        self::assertTrue($validation->isValid());
        $object->get('value');
        $native = new RecursiveArrayIterator(['value' => 'bad']);
        $object->unserialize($native->serialize());
        self::assertFalse($validation->isValid());
        self::assertSame('bad', $object->get('value'));
        self::assertSame(1, $object->getRevision());
    }

    public function testRecursiveIteratorChildrenRespectTheSnapshotPolicy(): void
    {
        $object = DataObject::snapshot(['record' => (object) ['value' => (object) ['age' => 12]]]);
        $object->rewind();
        $child = $object->getChildren();
        self::assertInstanceOf(DataObject::class, $child);
        $child->get('value')->age = 21;
        $child->set('value/age', 30);
        self::assertSame(12, $object->get('record/value/age'));
        self::assertSame(30, $child->get('value/age'));
        $legacy = new DataObject(['record' => ['age' => 12]]);
        $legacy->rewind();
        self::assertInstanceOf(DataObject::class, $legacy->getChildren());
    }

    public function testLegacyNumericPathsAndScalarCastNamesAreCoerced(): void
    {
        $validation = new Validation(new DataObject([12]), ['rules' => [
            ['path' => 0, 'pattern' => '#^[0-9]+$#', 'required' => 1],
        ]]);
        self::assertTrue($validation->isValid('0'));

        foreach ([123, false] as $type) {
            self::assertSame('12', (new DataFilters(['value' => ['type' => $type]]))->filterAt('value', '12'));
        }
    }

    /** @dataProvider invalidLegacyLimits */
    public function testLegacyNumericLimitsStillRejectInvalidAndNonFiniteValues($value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Middleware(['memory_limit' => $value]);
    }

    public function invalidLegacyLimits(): array
    {
        return [[' 0 '], ['-00'], ['1e999'], [INF], [NAN], ['not-bytes'], [false]];
    }

    public function testLegacyObjectFiltersCastTheSelectedObjectDirectly(): void
    {
        $object = Legacy::of(['record' => (object) ['age' => 12]], [
            'filters' => ['record' => ['type' => 'array']],
        ]);
        self::assertSame(['age' => 12], $object->get('record'));
    }

    public function testLegacyTreeLeafMatchingAndConsistentFullPathsAreExplicit(): void
    {
        $input = ['persons' => [['age' => '12']], 'other' => ['age' => '21'], 0 => ['age' => '30']];
        $options = ['persons/*/age' => ['type' => 'int']];
        $legacy = (new DataFilters($options))->filter('persons/0/age', $input);
        $consistent = DataFilters::consistent($options)->filter('persons/0/age', $input);
        self::assertSame(12, $legacy['persons'][0]['age']);
        self::assertSame(21, $legacy['other']['age']);
        self::assertSame(30, $legacy[0]['age']);
        self::assertSame('21', $consistent['other']['age']);
        self::assertSame('30', $consistent[0]['age']);
        self::assertSame('12', $input['persons'][0]['age']);
        self::assertSame('12', (new DataFilters(['age' => ['type' => 'int']]))->filter('age', ['age' => '12'])['age']);
    }

    public function testLegacyCallbackArgumentsKeepNamedKeysOnSupportedRuntimes(): void
    {
        $callback = static function ($value, $min, $max): bool {
            return $min <= $value && $value <= $max;
        };
        $filters = new DataFilters(['value' => ['type' => 'int', 'callback' => $callback,
            'args' => ['max' => 20, 'min' => 10]]]);
        self::assertSame(PHP_VERSION_ID >= 80000 ? 12 : false, $filters->filter('value', '12'));
    }

    public function testLegacyWildcardPrecedenceKeepsConfigurationOrderAfterExactMatches(): void
    {
        $options = ['groups/*/persons/*/age' => ['type' => 'float'],
            'groups/team/persons/*/age' => ['type' => 'int']];
        $path = 'groups/team/persons/u1/age';
        self::assertSame(12.0, (new DataFilters($options))->filter($path, '12'));
        self::assertSame(12, DataFilters::consistent($options)->filter($path, '12'));
        $options[$path] = ['type' => 'string'];
        self::assertSame('12', (new DataFilters($options))->filter($path, 12));
    }
}
