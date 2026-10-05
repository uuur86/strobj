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

use Error;
use InvalidArgumentException;
use ReflectionObject;
use ArrayAccess;
use ArrayObject;
use stdClass;
use Traversable;

/** Resolves paths independently of mutable iterator cursors. */
final class PathResolver
{
    /**
     * Copies values without changing their classes, or exports their JSON representation.
     *
     * @param mixed $value    Value to copy.
     * @param bool  $asArrays Convert all object containers to arrays.
     * @param int   $depth    Current recursive depth.
     *
     * @return mixed
     * @throws InvalidArgumentException For cyclic or excessively deep data.
     */
    public static function copyValue($value, bool $asArrays = false, int $depth = 0)
    {
        return $asArrays ? ValueCopier::toArray($value) : ValueCopier::copy($value, $depth);
    }

    /**
     * Creates a stdClass root whose fields are written through SPL storage.
     * Before PHP 8.1, SPL cannot find numeric property names created by an
     * (object) cast; writing them through SPL keeps every lookup consistent.
     *
     * @param array $entries Root fields.
     *
     * @return object
     */
    public static function objectFromEntries(array $entries): object
    {
        $object = new stdClass();
        $storage = new ArrayObject($object);

        foreach ($entries as $key => $value) {
            $storage[$key] = $value;
        }

        return $object;
    }

    /**
     * Checks key existence independently of null or false values.
     *
     * @param mixed  $node Container to inspect.
     * @param string $key  Key/property name.
     *
     * @return bool
     */
    public static function contains($node, string $key): bool
    {
        return self::lookup($node, $key)['exists'];
    }

    /** @return array{exists: bool, value: mixed} Reads arrays, public properties and collection protocols. */
    private static function lookup($node, string $key): array
    {
        if ($node instanceof Traversable) {
            return self::lookupTraversable($node, $key);
        }

        if ($node instanceof ArrayAccess) {
            $exists = $node->offsetExists($key);

            return ['exists' => $exists, 'value' => $exists ? $node[$key] : null];
        }

        $entries = self::entries($node);
        $exists = array_key_exists($key, $entries);

        return ['exists' => $exists, 'value' => $exists ? $entries[$key] : null];
    }

    /**
     * Finds a key by iterating a collection, so keys of any iterator are found.
     *
     * @param Traversable $node Collection to search.
     * @param string      $key  Key to find.
     *
     * @return array{exists: bool, value: mixed}
     */
    private static function lookupTraversable(Traversable $node, string $key): array
    {
        foreach ($node as $index => $value) {
            if ((string) $index === $key) {
                return ['exists' => true, 'value' => $value];
            }
        }

        return ['exists' => false, 'value' => null];
    }

    /**
     * Returns array entries or the public properties of an object; other values have no entries.
     * Properties are read outside the object's class scope, so non-public state stays hidden.
     *
     * @param mixed $node Value to inspect.
     *
     * @return array
     */
    public static function entries($node): array
    {
        if (is_object($node)) {
            return get_object_vars($node);
        }

        return is_array($node) ? $node : [];
    }

    /**
     * Reads one column of rows with the array_column() contract.
     * Rows without the column are skipped and the result is a list.
     * A scalar rows value produces an empty list.
     *
     * @param mixed         $rows      Rows container.
     * @param string[]      $prefix    Path segments of the rows container.
     * @param string        $column    Column name.
     * @param callable|null $transform Receives the concrete path and value.
     *
     * @return array
     */
    public static function column($rows, array $prefix, string $column, ?callable $transform = null): array
    {
        $values = [];

        foreach (is_array($rows) || is_object($rows) ? $rows : [] as $index => $row) {
            $fields = self::entries($row);

            if (array_key_exists($column, $fields)) {
                $path = implode('/', array_merge($prefix, [(string) $index, $column]));
                $values[] = $transform === null ? $fields[$column] : $transform($path, $fields[$column]);
            }
        }

        return $values;
    }

    /**
     * Reads a projection; wildcard levels produce nested lists with missing nulls.
     *
     * @param mixed         $node      Current subtree.
     * @param string[]      $segments  Remaining path segments.
     * @param callable|null $transform Optional concrete-path/value transformer.
     * @param string        $prefix    Current concrete path.
     * @param bool          $detached  Copy selected values instead of retaining references.
     *
     * @return mixed
     */
    public static function read(
        $node,
        array $segments,
        ?callable $transform = null,
        string $prefix = '',
        bool $detached = true
    ) {
        if ($segments === []) {
            $value = $detached ? self::copyValue($node) : $node;

            return $transform === null ? $value : $transform($prefix, $value);
        }

        $key = array_shift($segments);

        if ($key === '*') {
            $values = [];

            if (is_array($node) || is_object($node)) {
                foreach ($node as $index => $child) {
                    $values[] = self::read(
                        $child,
                        $segments,
                        $transform,
                        self::join($prefix, (string) $index),
                        $detached
                    );
                }
            }

            return $values;
        }

        $match = self::lookup($node, $key);

        return $match['exists']
        ? self::read($match['value'], $segments, $transform, self::join($prefix, $key), $detached)
        : null;
    }

    /**
     * Selects values and missing fields while preserving concrete paths.
     *
     * @param mixed    $node     Current subtree.
     * @param string[] $segments Remaining path segments.
     * @param string   $prefix   Current concrete path.
     * @param bool     $detached Copy selected values instead of retaining references.
     *
     * @return array<string, array{exists: bool, value: mixed}>
     */
    public static function select($node, array $segments, string $prefix = '', bool $detached = true): array
    {
        if ($segments === []) {
            return [$prefix => ['exists' => true, 'value' => $detached ? self::copyValue($node) : $node]];
        }

        $key = array_shift($segments);

        if ($key === '*') {
            $matches = [];

            if (is_array($node) || is_object($node)) {
                foreach ($node as $index => $child) {
                    $matches = array_replace($matches, self::select(
                        $child,
                        $segments,
                        self::join($prefix, (string) $index),
                        $detached
                    ));
                }
            }

            return $matches;
        }

        $match = self::lookup($node, $key);

        // A missing field reports the complete requested path below it.
        return $match['exists']
        ? self::select($match['value'], $segments, self::join($prefix, $key), $detached)
        : [self::join($prefix, implode('/', array_merge([$key], $segments))) => ['exists' => false, 'value' => null]];
    }

    /**
     * Builds a changed branch without mutating the original containers.
     *
     * @param mixed    $node     Current subtree.
     * @param string[] $segments Remaining concrete path segments.
     * @param mixed    $value    Detached value to write.
     * @param bool     $detached Copy writable object state before calling container setters.
     *
     * @return mixed
     */
    public static function write($node, array $segments, $value, bool $detached = true)
    {
        if ($segments === []) {
            return $value;
        }

        $key = array_shift($segments);

        if (!is_array($node) && !is_object($node)) {
            $node = [];
        }

        if (is_object($node)) {
            $copy = $detached ? self::copyValue($node) : self::cloneContainer($node);

            if ($copy === $node) {
                throw new InvalidArgumentException('Path writes require cloneable object containers.');
            }

            $node = $copy;
            $match = self::lookup($node, $key);
            $child = self::write($match['exists'] ? $match['value'] : [], $segments, $value, $detached);
            self::assign($node, $key, $child);
        } else {
            $child = array_key_exists($key, $node) ? $node[$key] : [];
            $node[$key] = self::write($child, $segments, $value, $detached);
        }

        return $node;
    }

    /**
     * Clones a container; uncloneable objects are returned as is and rejected by the caller.
     *
     * @param object $node Container to clone.
     *
     * @return object
     */
    private static function cloneContainer(object $node): object
    {
        return (new ReflectionObject($node))->isCloneable() ? clone $node : $node;
    }

    /**
     * Writes one field and reports PHP engine errors as invalid paths.
     * NUL-prefixed names, inaccessible or readonly properties and incompatible
     * typed properties would otherwise raise an uncatchable-by-Exception Error.
     *
     * @param object $node  Writable container.
     * @param string $key   Field name.
     * @param mixed  $value Value to store.
     *
     * @return void
     * @throws InvalidArgumentException When the container rejects the field.
     */
    private static function assign(object $node, string $key, $value): void
    {
        try {
            if ($node instanceof ArrayAccess) {
                $node[$key] = $value;
            } else {
                $node->{$key} = $value;
            }
        } catch (Error $error) {
            throw new InvalidArgumentException(
                sprintf('Cannot write the "%s" field of %s.', addcslashes($key, "\0..\37"), get_class($node)),
                0,
                $error
            );
        }
    }

    /**
     * Appends a key to a concrete path.
     *
     * @param string $prefix Parent path.
     * @param string $key    Next key.
     *
     * @return string
     */
    private static function join(string $prefix, string $key): string
    {
        return $prefix === '' ? $key : $prefix . '/' . $key;
    }
}
