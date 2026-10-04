<?php

/**
 * This file is part of the StrObj package.
 * (c) Uğur Biçer <contact@codeplus.dev>
 * See LICENSE for copyright and license information.
 */

declare(strict_types=1);

namespace StrObj\Helpers;

use InvalidArgumentException;
use ArrayAccess;
use ArrayIterator;
use ArrayObject;
use Closure;
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
            $result = [];

            foreach ($value as $key => $item) {
                $result[$key] = self::copyNode($item, $depth + 1, $active);
            }

            return $result;
        }

        if (!is_object($value)) {
            return $value;
        }

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

            do {
                foreach ($reflection->getProperties() as $property) {
                    if (
                        $property->isStatic()
                        || $property->getDeclaringClass()->getName() !== $reflection->getName()
                    ) {
                        continue;
                    }

                    if (
                        !$property->isInitialized($result)
                        || (method_exists($property, 'isReadOnly') && $property->isReadOnly())
                        || (method_exists($property, 'isVirtual') && $property->isVirtual())
                    ) {
                        continue;
                    }

                    $access = self::propertyAccessor($property);
                    $name = $property->getName();
                    $copy = self::copyNode($access($result, $name, false), $depth + 1, $active);
                    $access($result, $name, true, $copy);
                }

                $reflection = $reflection->getParentClass();
            } while ($reflection !== false);

            // Copy writable collection entries through their public container protocol.
            if (!self::copySplStorage($result, $depth, $active)) {
                if ($result instanceof Traversable && $result instanceof ArrayAccess) {
                    foreach ($result as $key => $item) {
                        $result[$key] = self::copyNode($item, $depth + 1, $active);
                    }
                }
            }

            return $result;
        } finally {
            unset($active[$value]);
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
