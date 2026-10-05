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

namespace StrObj;

use ArrayIterator;
use InvalidArgumentException;
use JsonException;
use StrObj\Data\DataFilters;
use StrObj\Data\DataObject;
use StrObj\Data\Validation;
use StrObj\Helpers\PathResolver;

/**
 * StringObjects class
 *
 * @phpstan-consistent-constructor Subclasses keep the constructor accepted by instance().
 */
class StringObjects
{
    /** Exception code: the JSON input cannot be decoded. */
    public const ERROR_INVALID_JSON = 22;

    /** Exception code: the JSON input contains a scalar instead of an object or array. */
    public const ERROR_SCALAR_JSON = 23;

    /** Exception code: the input is neither an array, an object nor a JSON string. */
    public const ERROR_UNSUPPORTED_INPUT = 24;

    /**
     * The main data object
     *
     * @var DataObject
     */
    private DataObject $data;
    /**
     * Validation object
     *
     * @var Validation
     */
    private Validation $validation;
    /**
     * Middleware object
     *
     * @var Middleware
     */
    private Middleware $middleware;
    /**
     * Filters object
     *
     * @var DataFilters
     */
    private DataFilters $filters;
    /**
     * Whether output filters are configured.
     *
     * @var bool
     */
    private bool $hasFilters;
    /** @var bool Whether the consistent behavior (the default) is enabled. */
    private bool $consistent;

    /**
     * Constructor
     *
     * @param object       $obj     The object to use
     * @param array        $options Options
     */
    public function __construct(object $obj, array $options = [])
    {
        $this->consistent = Behavior::isConsistent($options);

        foreach (['middleware', 'validation', 'filters'] as $option) {
            if (isset($options[$option]) && !is_array($options[$option])) {
                throw new InvalidArgumentException($option . ' options must be an array.');
            }
        }

        $this->middleware = new Middleware($options['middleware'] ?? [], $this->consistent);
        $this->middleware->memoryLeakProtection();
        $this->data = $this->consistent ? DataObject::snapshot($obj) : new DataObject($obj);
        $this->validation = new Validation($this->data, $options['validation'] ?? [], $this->consistent);
        $this->validation->validate();
        $this->filters = new DataFilters($options['filters'] ?? [], $this->consistent);
        $this->hasFilters = !empty($options['filters']);
        $this->middleware->memoryLeakProtection();
    }

    /**
     * Creates an instance from an array, any object (including Traversable) or a JSON document
     * The consistent behavior is the default. Pass ['behavior' => Behavior::LEGACY]
     * to reproduce the v2.1 results.
     *
     * @param mixed $data    The mixed type of object data to use
     * @param array $options Behavior, middleware, validation and filter options
     *
     * @throws InvalidArgumentException With code ERROR_INVALID_JSON (22), ERROR_SCALAR_JSON (23)
     *                                  or ERROR_UNSUPPORTED_INPUT (24).
     *
     * @return self|static The legacy behavior returns self; the consistent behavior preserves subclasses.
     */
    public static function instance($data, array $options = [])
    {
        $decoded = is_string($data);

        if ($decoded) {
            $data = self::decodeJson($data);
        } elseif (!is_array($data) && !is_object($data)) {
            throw new InvalidArgumentException(
                'Input data is neither an object nor an array.',
                self::ERROR_UNSUPPORTED_INPUT
            );
        }

        if (Behavior::isConsistent($options)) {
            // Snapshots copy the entries of object roots, so only arrays need an iterator.
            return new static(is_array($data) ? new ArrayIterator($data) : $data, $options);
        }

        // Arrays and decoded roots have no outside references, so they can be rebuilt with consistent keys.
        if ($decoded || is_array($data)) {
            $data = PathResolver::objectFromEntries((array) $data);
        }

        return new self($data, $options);
    }

    /**
     * Decodes a JSON document whose root is an object or an array.
     *
     * @param string $json The JSON document.
     *
     * @throws InvalidArgumentException With code ERROR_INVALID_JSON (22) or ERROR_SCALAR_JSON (23).
     *
     * @return array|object
     */
    private static function decodeJson(string $json)
    {
        $decoded = json_decode($json);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                'JSON decoding error: ' . json_last_error_msg(),
                self::ERROR_INVALID_JSON
            );
        }

        if (!is_array($decoded) && !is_object($decoded)) {
            throw new InvalidArgumentException(
                'JSON input must contain an object or array.',
                self::ERROR_SCALAR_JSON
            );
        }

        return $decoded;
    }

    /**
     * Gets the value from the inside of the loaded object
     * Legacy behavior substitutes the default for false and returns null for missing fields.
     * Consistent behavior returns stored values unchanged, including false and null.
     * It substitutes the default only for a missing concrete field or a value
     * rejected by a filter callback. Wildcard reads list missing fields as null.
     *
     * @param string|null $path    requested object path like
     *                        data/child_data instead of data->child_data
     * @param mixed  $default value returned for missing (consistent: or rejected) values
     *
     * @return mixed          returns the value or default value (false)
     */
    public function get(?string $path = '', $default = false)
    {
        $this->middleware->memoryLeakProtection();
        $path = $path ?? '';

        return $this->consistent ? $this->readConsistent($path, $default) : $this->readLegacy($path, $default);
    }

    /**
     * Reads a value with the v2.1 contract: false and missing fields both yield the default.
     *
     * @param string $path    Requested path.
     * @param mixed  $default Value returned for false or missing values.
     *
     * @return mixed
     */
    private function readLegacy(string $path, $default)
    {
        $result = $this->data->get($path);

        if ($result === false) {
            return $default;
        }

        return $this->hasFilters ? $this->filters->filter($path, $result) : $result;
    }

    /**
     * Reads a value unchanged; the default replaces only missing or rejected values.
     *
     * @param string $path    Requested path.
     * @param mixed  $default Value returned for a missing concrete field or a rejected value.
     *
     * @return mixed
     */
    private function readConsistent(string $path, $default)
    {
        if (strpos($path, '*') === false && !$this->data->has($path)) {
            return $default;
        }

        if (!$this->hasFilters) {
            return $this->data->get($path);
        }

        // A value rejected by a filter callback is replaced by the default, never by false.
        return $this->data->queryWithTransform($path, function (string $concrete, $value) use ($default) {
            return $this->filters->filterAt($concrete, $value, $default);
        });
    }

    /**
     * Sets the value to the inside of the loaded object
     *
     * @param string $path  requested object path like
     *                      data/child_data instead of data->child_data
     * @param mixed  $value value to be set
     *
     * @return void
     */
    public function set(string $path, $value): void
    {
        $this->middleware->memoryLeakProtection();
        $this->data->set($path, $value);
    }

    /**
     * Checks if the value exists in the loaded object
     *
     * @param string $path requested object path like
     *                     data/child_data instead of data->child_data
     *
     * @return bool
     */
    public function has(string $path): bool
    {
        $this->middleware->memoryLeakProtection();

        return $this->data->has($path);
    }

    /**
     * Returns the object as a JSON string
     *
     * @throws JsonException When data cannot be JSON encoded, for example NAN or invalid UTF-8.
     *
     * @return string
     */
    public function toJson(): string
    {
        $this->middleware->memoryLeakProtection();

        if ($this->consistent) {
            return json_encode($this->data->toJsonValue(), JSON_THROW_ON_ERROR);
        }

        $json = json_encode($this->data);

        if ($json === false) {
            throw new JsonException(json_last_error_msg(), json_last_error());
        }

        return $json;
    }

    /**
     * Returns the object as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $this->middleware->memoryLeakProtection();

        return $this->data->toArray();
    }

    /**
     * Checks current data against configured rules; no rules means valid
     *
     * @param string $path data path ( /data/0/text )
     *
     * @return bool
     */
    public function isValid(string $path = ''): bool
    {
        $this->middleware->memoryLeakProtection();

        return $this->validation->isValid($path);
    }

    /**
     * Sets this instance's memory guard without changing php.ini
     * The guard compares the whole PHP process's memory usage with the limit.
     *
     * @param int $memory memory limit in megabytes
     *
     * @return void
     */
    public function setMemoryLimit(int $memory): void
    {
        $this->middleware->setMemoryLimit($memory);
    }
}
