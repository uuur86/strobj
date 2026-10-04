<?php

/**
 * This file is part of the StrObj package.
 *
 * (c) Uğur Biçer <contact@fyndsoft.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package  StrObj
 * @version  GIT: <git_id>
 * @link     https://github.com/uuur86/strobj
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
    private DataObject $_obj;
    /**
     * Validation object
     *
     * @var Validation
     */
    private Validation $_validation;
    /**
     * Middleware object
     *
     * @var Middleware
     */
    private Middleware $_middleware;
    /**
     * Filters object
     *
     * @var DataFilters
     */
    private DataFilters $_filters;
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

        $this->_middleware = new Middleware($options['middleware'] ?? [], $this->consistent);
        $this->_middleware->memoryLeakProtection();
        $this->_obj = $this->consistent ? DataObject::snapshot($obj) : new DataObject($obj);
        $this->_validation = new Validation($this->_obj, $options['validation'] ?? [], $this->consistent);
        $this->_validation->validate();
        $this->_filters = new DataFilters($options['filters'] ?? [], $this->consistent);
        $this->hasFilters = !empty($options['filters']);
        $this->_middleware->memoryLeakProtection();
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
        if (is_string($data)) {
            $decoded = json_decode($data);

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

            // A decoded root has no outside references, so it can be rebuilt with consistent keys.
            $data = is_object($decoded) ? PathResolver::objectFromEntries((array) $decoded) : $decoded;
        } elseif (!is_array($data) && !is_object($data)) {
            throw new InvalidArgumentException(
                'Input data is neither an object nor an array.',
                self::ERROR_UNSUPPORTED_INPUT
            );
        }

        $consistent = Behavior::isConsistent($options);

        if (is_array($data)) {
            $data = $consistent ? new ArrayIterator($data) : PathResolver::objectFromEntries($data);
        }

        return $consistent ? new static($data, $options) : new self($data, $options);
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
        $this->_middleware->memoryLeakProtection();
        $path = $path ?? '';

        if (!$this->consistent) {
            $result = $this->_obj->get($path);

            if ($result === false) {
                return $default;
            }

            return $this->hasFilters ? $this->_filters->filter($path, $result) : $result;
        }

        if (strpos($path, '*') === false && !$this->_obj->has($path)) {
            return $default;
        }

        if (!$this->hasFilters) {
            return $this->_obj->get($path);
        }

        // A value rejected by a filter callback is replaced by the default, never by false.
        return $this->_obj->queryWithTransform($path, function (string $concrete, $value) use ($default) {
            return $this->_filters->filterAt($concrete, $value, $default);
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
        $this->_middleware->memoryLeakProtection();
        $this->_obj->set($path, $value);
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
        $this->_middleware->memoryLeakProtection();

        return $this->_obj->has($path);
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
        $this->_middleware->memoryLeakProtection();

        if ($this->consistent) {
            return json_encode($this->_obj->toJsonValue(), JSON_THROW_ON_ERROR);
        }

        $json = json_encode($this->_obj);

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
        $this->_middleware->memoryLeakProtection();

        return $this->_obj->toArray();
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
        $this->_middleware->memoryLeakProtection();

        return $this->_validation->isValid($path);
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
        $this->_middleware->setMemoryLimit($memory);
    }
}
