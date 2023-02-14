<?php

/**
 * StrObj: PHP String Object Project
 * It enables you to access any objects and arrays readily without
 * any problem and in a safe manner.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package strobj
 * @license GPLv2
 * @author Uğur Biçer <info@ugurbicer.com.tr>
 * @version 2.1.3.1
 */

declare(strict_types=1);

namespace StrObj;

use OverflowException;
use UnexpectedValueException;
use StrObj\Data\DataFilters;
use StrObj\Data\DataObject;
use StrObj\Data\Validation;
use StrObj\Interfaces\DataStructures\DataInterface;

use function is_array;
use function is_string;
use function substr;
use function strlen;
use function sprintf;
use function strtolower;
use function preg_match;
use function ini_get;
use function memory_get_usage;

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
     * @param object|array $data  The object to use
     * @param array        $options Options
     */
    public function __construct($data, array $options = [])
    {
        $this->obj = new DataObject((object)$data);

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
     * @param mixed $obj        The object to use
     * @param array $options    Options
     *
     * @return bool|static
     */
    public static function instance($obj, array $options = [])
    {
        if (is_string($obj)) {
            $obj = json_decode($obj);
        }

        return new static($obj, $options);
    }

    /**
     * Gets the value from the inside of the loaded object
     *  or returns the default value
     *
     * @param string $path      requested object path like
     *                          data/child_data instead of data->child_data
     * @param mixed  $default   default value will return if value not exists
     *
     * @return mixed            returns the value or default value (false)
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
     * @param string $path       requested object path like
     *                           data/child_data instead of data->child_data
     * @param mixed  $value      value to be set
     */
    public function set(string $path, $value): void
    {
        $this->obj->set($path, $value);
    }

    /**
     * Checks if the value exists in the loaded object
     *
     * @param string $path      requested object path like
     *                          data/child_data instead of data->child_data
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
     * @return bool
     */
    public function isValid(string $path): bool
    {
        return $this->validation->isValid($path);
    }

    /**
     * Set a memory limit
     *
     * @param int $memory  memory limit
     */
    public function setMemoryLimit(int $memory): void
    {
        $this->middleware->setMemoryLimit($memory);
    }
}
