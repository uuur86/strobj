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

namespace StrObj\Data;

use InvalidArgumentException;
use Closure;
use ArrayAccess;
use StrObj\Helpers\Adapters;
use StrObj\Helpers\DataParsers;
use StrObj\Helpers\PathResolver;

class DataFilters
{
    /**
     * Adapter trait
     */
    use Adapters;
    /**
     * DataParsers trait
     */
    use DataParsers;

    /**
     * @var array
     */
    private array $options;
    /** @var bool Whether configuration and casts use strict behavior. */
    private bool $consistent;

    /**
     * Constructor
     *
     * @param array $options
     * @param bool $consistent Enable the explicit consistent behavior profile.
     */
    public function __construct(array $options, bool $consistent = false)
    {
        $this->consistent = $consistent;

        foreach ($options as $filters) {
            if (
                $consistent && (!is_array($filters) || (isset($filters['type']) && !is_string($filters['type'])) ||
                (isset($filters['callback']) && !is_callable($filters['callback'])))
            ) {
                throw new InvalidArgumentException('Filters require an array, string type and callable callback.');
            }
        }

        $this->options = $options;
    }
    /** @return self A filter container with strict casts and callable predicates. */
    public static function consistent(array $options): self
    {
        return new self($options, true);
    }

    /**
     * Filters one selected value using its complete concrete path.
     *
     * @param string $path     Concrete data path.
     * @param mixed  $value    Selected value.
     * @param mixed  $rejected Returned when the callback rejects the value.
     *
     * @return mixed Filtered value, $rejected when rejected, or unchanged when unmatched.
     */
    public function filterAt(string $path, $value, $rejected = false)
    {
        $optionPath = $this->findFilterPath($path);

        if (!array_key_exists($optionPath, $this->options) || !$this->matchesPath($optionPath, $path)) {
            return $value;
        }

        return $this->filterValue($value, $this->options[$optionPath], $rejected);
    }

    /**
     * Applies the selected filter to a scalar or matching paths in a full data tree.
     * Consistent mode copies the tree and matches full paths.
     * Legacy mode retains array leaf-name matching and direct object casts.
     *
     * @param string $path
     * @param mixed $data
     *
     * @return mixed
     */
    public function filter(string $path, $data)
    {
        $optionPath = $this->findFilterPath($path);

        if (!array_key_exists($optionPath, $this->options) || !$this->matchesPath($optionPath, $path)) {
            return $data;
        }

        if (!is_array($data) && (!$this->consistent || !is_object($data))) {
            return $this->filterValue($data, $this->options[$optionPath]);
        }

        $data = $this->consistent ? PathResolver::copyValue($data) : $data;

        return $this->filterTree($data, '', $optionPath, $this->options[$optionPath]);
    }

    /** @return string Exact filter first; otherwise profile-specific wildcard precedence. */
    private function findFilterPath(string $path): string
    {
        if (isset($this->options[$path])) {
            return $path;
        }

        return $this->selectMatchingPath($path, $this->options, $this->consistent);
    }

    /**
     * Applies one filter to matching paths inside a complete data tree.
     *
     * @param mixed  $node    Current subtree.
     * @param string $path    Current concrete path.
     * @param string $pattern Full filter pattern.
     * @param array  $filters Filter configuration.
     * @param mixed  $key     Original entry key, retained for legacy leaf matching.
     *
     * @return mixed
     */
    private function filterTree($node, string $path, string $pattern, array $filters, $key = null)
    {
        $matches = $this->consistent ? $this->matchesPath($pattern, $path)
        : (!is_array($node) && substr($pattern, (int) strrpos($pattern, '/') + 1) === $key);

        if ($matches) {
            return $this->filterValue($node, $filters);
        }

        if (is_array($node) || ($this->consistent && is_object($node))) {
            foreach ($node as $index => $value) {
                $concrete = $path === '' ? (string) $index : $path . '/' . $index;
                $value = $this->filterTree($value, $concrete, $pattern, $filters, $index);

                if (is_array($node) || $node instanceof ArrayAccess) {
                    $node[$index] = $value;
                } else {
                    $node->{(string) $index} = $value;
                }
            }
        }

        return $node;
    }

    /**
     * Filter data on a specific value
     *
     * @param mixed $value
     * @param array $filters
     * @param mixed $rejected Returned when the callback rejects the value.
     *
     * @return mixed
     */
    private function filterValue($value, array $filters, $rejected = false)
    {
        $type = $filters['type'] ?? 'string';
        $type = $this->consistent ? $type : (string) $type;
        $value = $this->consistent ? $this->castTypeStrict($value, $type) : $this->castType($value, $type);
        $callback = $filters['callback'] ?? null;

        if ($callback === null || (!$this->consistent && !$callback instanceof Closure)) {
            return $value;
        }

        $arguments = $filters['args'] ?? [];
        $arguments = is_array($arguments)
        ? ($this->consistent ? array_values($arguments) : $arguments) : [$arguments];

        return call_user_func_array($callback, array_merge([$value], $arguments)) ? $value : $rejected;
    }
}
