<?php

/**
 * This file is part of the StrObj package.
 *
 * (c) Uğur Biçer <contact@fyndsoft.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package StrObj
 * @link    https://github.com/uuur86/strobj
 */

declare(strict_types=1);

namespace StrObj\Helpers;

use InvalidArgumentException;
use ArrayAccess;
use ArrayIterator;
use ArrayObject;
use Closure;
use ReflectionClass;
use ReflectionObject;
use ReflectionProperty;
use ReflectionMethod;
use SplObjectStorage;
use Traversable;

/** Copies values without flattening object classes or bypassing their serializers. */
final class ValueCopier
{
    /**
     * Clones object state and recursively copies writable properties and array entries.
     * Readonly and opaque internal state follow the object's native clone contract.
     * Uncloneable values retain their identity, just like resources and other handles.
     *
     * @param mixed $value Value to copy.
     * @param int $depth Existing recursion depth.
     * @return mixed
     * @throws InvalidArgumentException For cyclic or excessively deep structures.
     */
    public static function copy($value, int $depth = 0)
    {
        return self::copyNode($value, $depth, new SplObjectStorage());
    }

    /** Exports through PHP's serialization contract, retaining custom JsonSerializable output. */
    public static function toArray($value)
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Tracks the active object path; repeated non-cyclic values may be copied independently. */
    private static function copyNode($value, int $depth, SplObjectStorage $active)
    {
        if ($depth > 512) {
            throw new InvalidArgumentException('Data is cyclic or exceeds the maximum depth of 512.');
        }

        if (is_array($value)) {
            return self::copyArray($value, $depth, $active);
        }

        return is_object($value) ? self::copyObject($value, $depth, $active) : $value;
    }

    /**
     * Copies every array entry, preserving keys.
     *
     * @return array
     */
    private static function copyArray(array $value, int $depth, SplObjectStorage $active): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            $result[$key] = self::copyNode($item, $depth + 1, $active);
        }

        return $result;
    }

    /**
     * Clones an object and copies its writable state declared at every class level.
     * Uncloneable objects retain their identity.
     *
     * @return object
     * @throws InvalidArgumentException For cyclic object graphs.
     */
    private static function copyObject(object $value, int $depth, SplObjectStorage $active): object
    {
        if (isset($active[$value])) {
            throw new InvalidArgumentException('Data is cyclic or exceeds the maximum depth of 512.');
        }

        $reflection = new ReflectionObject($value);

        if (!$reflection->isCloneable()) {
            return $value;
        }

        $active[$value] = null;

        try {
            $result = clone $value;

            // ReflectionObject also lists dynamic properties; each parent level adds its private state.
            for ($class = $reflection; $class !== false; $class = $class->getParentClass()) {
                self::copyDeclaredState($result, $class, $depth, $active);
            }

            // Copy writable collection entries through their public container protocol.
            if (
                !self::copySplStorage($result, $depth, $active)
                && $result instanceof Traversable
                && $result instanceof ArrayAccess
            ) {
                self::copyEntries($result, $depth, $active);
            }

            return $result;
        } finally {
            unset($active[$value]);
        }
    }

    /**
     * Copies the initialized, writable properties that one class level declares.
     *
     * @return void
     */
    private static function copyDeclaredState(
        object $object,
        ReflectionClass $class,
        int $depth,
        SplObjectStorage $active
    ): void {
        foreach ($class->getProperties() as $property) {
            if (!self::isCopyable($property, $class, $object)) {
                continue;
            }

            $access = self::propertyAccessor($property);
            $name = $property->getName();
            $copy = self::copyNode($access($object, $name, false), $depth + 1, $active);
            $access($object, $name, true, $copy);
        }
    }

    /**
     * Reports whether a property is declared by this class level, initialized and writable.
     * Readonly and virtual properties follow the native clone contract.
     *
     * @return bool
     */
    private static function isCopyable(ReflectionProperty $property, ReflectionClass $class, object $object): bool
    {
        if ($property->isStatic() || $property->getDeclaringClass()->getName() !== $class->getName()) {
            return false;
        }

        return self::isInitialized($property, $object)
        && !(method_exists($property, 'isReadOnly') && $property->isReadOnly())
        && !(method_exists($property, 'isVirtual') && $property->isVirtual());
    }

    /**
     * Copies the entries of a writable collection that has no native SPL storage.
     *
     * @param ArrayAccess&Traversable $collection Collection to update.
     *
     * @return void
     */
    private static function copyEntries(ArrayAccess $collection, int $depth, SplObjectStorage $active): void
    {
        foreach ($collection as $key => $item) {
            $collection[$key] = self::copyNode($item, $depth + 1, $active);
        }
    }

    /**
     * Reinitializes SPL's native backing storage; clone alone can retain storage aliases.
     * Invoking the native constructor preserves subclasses without re-running user constructors.
     *
     * @return bool Whether native array storage was handled.
     */
    private static function copySplStorage(object $object, int $depth, SplObjectStorage $active): bool
    {
        if ($object instanceof ArrayObject) {
            $base = ArrayObject::class;
        } elseif ($object instanceof ArrayIterator) {
            $base = ArrayIterator::class;
        } else {
            return false;
        }

        $entries = (new ReflectionMethod($base, 'getArrayCopy'))->invoke($object);
        $arguments = [self::copyNode($entries, $depth + 1, $active),
            (new ReflectionMethod($base, 'getFlags'))->invoke($object)];

        if ($object instanceof ArrayObject) {
            $arguments[] = (new ReflectionMethod($base, 'getIteratorClass'))->invoke($object);
        }

        (new ReflectionMethod($base, '__construct'))->invokeArgs($object, $arguments);

        return true;
    }

    /**
     * Checks initialization in the declaring scope; get_object_vars() omits
     * uninitialized and unset properties. Unlike ReflectionProperty::isInitialized(),
     * this needs no setAccessible() call for private state before PHP 8.1.
     *
     * @return bool
     */
    private static function isInitialized(ReflectionProperty $property, object $object): bool
    {
        $check = static function (object $object, string $name): bool {
            return array_key_exists($name, get_object_vars($object));
        };
        $scope = $property->getDeclaringClass();
        $check = $scope->isInternal() ? $check : Closure::bind($check, null, $scope->getName());

        return $check($object, $property->getName());
    }

    /**
     * Reads/writes declared state in its declaring scope without deprecated reflection APIs.
     * User-defined properties use their declaring scope, including asymmetric setters.
     * Internal public properties use ordinary access and keep the native clone contract.
     *
     * @return Closure
     */
    private static function propertyAccessor(ReflectionProperty $property): Closure
    {
        $access = static function (object $object, string $name, bool $write, $value = null) {
            if ($write) {
                $object->{$name} = $value;
            }

            return $object->{$name};
        };

        return $property->getDeclaringClass()->isInternal()
        ? $access : Closure::bind($access, null, $property->getDeclaringClass()->getName());
    }
}
