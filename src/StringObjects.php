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
 * @version 2.0.0
 */

declare(strict_types=1);

namespace StrObj;

use OverflowException;
use StrObj\Data\DataCache;
use StrObj\Interfaces\DataStructures\DataInterface;
use UnexpectedValueException;
use StrObj\Data\DataObject;
use StrObj\Data\Validation;

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
     * Latest query path
     *
     * @var string
     */
    private ?string $currentPath = null;

    /**
     * Cached object values
     *
     * @var array
     */
    private array $paths = [];

    /**
     * Constructor
     *
     * @param object|array    $obj        The object to use
     */
    public function __construct(object $data)
    {
        $this->obj   = new DataObject($data);
        $this->cache = new DataCache();
        $this->validation = new Validation($this->obj);
        $this->middleware = new Middleware();
    }

    /**
     * You can provide an array or any traversable object
     *
     * @param object  $obj        The object to use
     * @param int     $memory     The memory limit
     *
     * @return bool|static
     */
    public static function instance($obj)
    {
        if (is_string($obj)) {
            $obj = json_decode($obj);
        }

        if (!$obj || empty($obj)) {
            return $obj;
        }

        return new static($obj);
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
        if ($this->cache->isCached($path)) {
            return $this->cache->get($path);
        }

        $result = $this->obj->get($path);

        if ($result === false) {
            return $default;
        }

        $this->cache->save($path, $result);

        return $result;
    }

    /**
     * Sets the value to the inside of the loaded object
     *
     * @param string $path       requested object path like
     *                           data/child_data instead of data->child_data
     * @param mixed  $value      value to be set
     *
     * @return bool
     */
    public function set(string $path, $value): bool
    {
        $objData = $this->obj->set($path, $value);

        if ($objData instanceof Collection) {
            $this->obj = $objData;
            return true;
        }

        return false;
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
     * Adds a validator to the object
     */
    public function addValidator(string $path, callable $validator): void
    {
        $this->validation->add($path, $validator);
    }

    /**
     * Set a memory limit
     */
    public function setMemoryLimit(int $memory): void
    {
        $this->middleware->setMemoryLimit($memory);
    }
}
