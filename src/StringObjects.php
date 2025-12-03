<?php

/**
 * This file is part of the StrObj package.
 *
 * (c) Uğur Biçer <contact@codeplus.dev>
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

use Exception;
use StrObj\Data\DataFilters;
use StrObj\Data\DataObject;
use StrObj\Data\Validation;

use function is_string;

/**
 * StringObjects class
 */
class StringObjects
{
    use Helpers\Adapters;

    /**
     * The main data object
     *
     * @var DataObject
     */
    private DataObject $obj;

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
     * Constructor
     *
     * @param object $obj     The object to use
     * @param array  $options Options
     */
    final public function __construct(object $obj, array $options = [])
    {
        $this->obj = new DataObject($obj);

        if (isset($options['middleware'])) {
            $this->middleware = new Middleware($options['middleware']);
            $this->middleware->memoryLeakProtection();
        }

        if (isset($options['validation'])) {
            $this->validation = new Validation($this->obj, $options['validation']);
            $this->validation->validate();
        }

        if (isset($options['filters'])) {
            $this->filters = new DataFilters($options['filters']);
        }
    }

    /**
     * You can provide an array or any traversable object
     *
     * @param mixed $data    The mixed type of object data to use
     * @param array $options Options
     *
     * @return static
     * @throws Exception
     */
    public static function instance($data, array $options = [])
    {
        if (is_string($data)) {
            $decodedData = json_decode($data);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON decoding error: " . json_last_error_msg(), 22);
            }

            $data = $decodedData;
        } elseif (! is_array($data) && ! is_object($data)) {
            throw new Exception("Input data is neither an object nor an array.", 24);
        }

        if (is_array($data)) {
            $data = (object) $data;
        }

        if (!is_object($data)) {
            throw new Exception("Input data is not a valid object!\r\n" . print_r($data, true), 23);
        }

        return new static($data, $options);
    }

    /**
     * Gets the value from the inside of the loaded object
     *  or returns the default value
     *
     * @param string $path    requested object path like
     *                        data/child_data instead of data->child_data
     * @param mixed  $default default value will return if value not exists
     *
     * @return mixed          returns the value or default value (false)
     */
    public function get(?string $path = '', $default = false)
    {
        $result = $this->obj->get($path);

        if ($result === false) {
            return $default;
        }

        if (isset($this->filters)) {
            $result = $this->filters->filter($path, $result);
        }

        return $result;
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
        $this->obj->set($path, $value);
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
        return $this->obj->has($path);
    }

    /**
     * Returns the object as a JSON string
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->obj);
    }

    /**
     * Returns the object as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->obj->toArray();
    }

    /**
     * The object is valid or not
     *
     * @param string $path data path ( /data/0/text )
     *
     * @return bool
     */
    public function isValid(string $path = ''): bool
    {
        return $this->validation->isValid($path);
    }

    /**
     * Set a memory limit
     *
     * @param int $memory memory limit
     *
     * @return void
     */
    public function setMemoryLimit(int $memory): void
    {
        $this->middleware->setMemoryLimit($memory);
    }
}
