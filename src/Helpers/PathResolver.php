<?php

/**
 * This file is part of the StrObj package.
 * (c) Uğur Biçer <contact@fyndsoft.com>
 * See LICENSE for copyright and license information.
 */

declare(strict_types=1);

namespace StrObj\Helpers;

use InvalidArgumentException;
use ArrayAccess;
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
            foreach ($node as $index => $value) {
                if ((string) $index === $key) {
                    return ['exists' => true, 'value' => $value];
                }
            }

            return ['exists' => false, 'value' => null];
        }

        if ($node instanceof ArrayAccess) {
            $exists = $node->offsetExists($key);

            return ['exists' => $exists, 'value' => $exists ? $node[$key] : null];
        }

        $entries = is_array($node) ? $node : (is_object($node) ? get_object_vars($node) : []);
        $exists = array_key_exists($key, $entries);

        return ['exists' => $exists, 'value' => $exists ? $entries[$key] : null];
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

        if (!$match['exists']) {
            return null;
        }

        $child = $match['value'];

        return self::read($child, $segments, $transform, self::join($prefix, $key), $detached);
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

        if (!$match['exists']) {
            $missing = self::join($prefix, implode('/', array_merge([$key], $segments)));

            return [$missing => ['exists' => false, 'value' => null]];
        }

        $child = $match['value'];

        return self::select($child, $segments, self::join($prefix, $key), $detached);
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
            $copy = $detached ? self::copyValue($node) : clone $node;

            if ($copy === $node) {
                throw new InvalidArgumentException('Path writes require cloneable object containers.');
            }

            $node = $copy;
            $match = self::lookup($node, $key);
            $child = self::write($match['exists'] ? $match['value'] : [], $segments, $value, $detached);

            if ($node instanceof ArrayAccess) {
                $node[$key] = $child;
            } else {
                $node->{$key} = $child;
            }
        } else {
            $child = array_key_exists($key, $node) ? $node[$key] : [];
            $node[$key] = self::write($child, $segments, $value, $detached);
        }

        return $node;
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
