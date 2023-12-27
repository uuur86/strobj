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
     * Constructor
     *
     * @param object $obj     The object to use
     * @param array  $options Options
     */
    public function __construct(object $obj, array $options = [])
    {
        $this->_obj = new DataObject($obj);

        if (isset($options['middleware'])) {
            $this->_middleware = new Middleware($options['middleware']);
            $this->_middleware->memoryLeakProtection();
        }

        if (isset($options['validation'])) {
            $this->_validation = new Validation($this->_obj, $options['validation']);
            $this->_validation->validate();
        }

        if (isset($options['filters'])) {
            $this->_filters = new DataFilters($options['filters']);
        }
    }

    /**
     * You can provide an array or any traversable object
     *
     * @param mixed $data    The mixed type of object data to use
     * @param array $options Options
     *
     * @return static|bool
     */
    public static function instance($data, array $options = [])
    {
        if (is_string($data)) {
            $data = json_decode($data);
        }

        if (! is_object($data)) {
            try {
                $data = (object) $data;
            } catch (Exception) {
                throw new Exception("Input data is not valid!\r\n" . print_r($data, true), 23);
            }
        }

        return new self($data, $options);
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
        $result = $this->_obj->get($path);

        if ($result === false) {
            return $default;
        }

        if (isset($this->_filters)) {
            $result = $this->_filters->filter($path, $result);
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
        return $this->_obj->has($path);
    }

    /**
     * Returns the object as a JSON string
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->_obj);
    }

    /**
     * Returns the object as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->_obj->toArray();
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
        return $this->_validation->isValid($path);
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
        $this->_middleware->setMemoryLimit($memory);
    }
}
