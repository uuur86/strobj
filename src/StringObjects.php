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
 * @version 1.0.2
 */

declare(strict_types=1);

namespace StrObj;

use OverflowException;
use UnexpectedValueException;

use function next;
use function current;
use function key;
use function is_array;
use function is_string;
use function substr;
use function strlen;
use function sprintf;
use function strtolower;
use function preg_match;
use function explode;
use function implode;
use function ini_get;
use function memory_get_usage;

class StringObjects
{
  /**
   * The object to use
   *
   * @var object|array
   */
    private $obj = null;

  /**
   * Latest query path info
   *
   * @var string
   */
    private $currentPath = null;

  /**
   * Stored object values
   *
   * @var array
   */
    private $paths = [];

  /**
   * Query results
   *
   * @var array
   */
    private $results = [];

  /**
   * The object paths about validation errors
   *
   * @var array
   */
    private $validationErrors = [];

  /**
   * User defined regex templates
   *
   * @var array
   */
    private $regexType = [];

  /**
   * Memory limit in bytes
   *
   * @var int
   */
    private $memoryLimit = 0;

  /**
   * Constructor
   *
   * @param object|array $obj The object to use
   * @param int $memory The memory limit
   */
    public function __construct($obj, int $memory)
    {
        $this->obj = $obj;

        $this->setMemoryLimit($memory);
    }

  /**
   * You can provide an array or any traversable object
   *
   * @param object $obj The object to use
   * @param int $memory The memory limit
   *
   * @return bool|static
   */
    public static function instance($obj, int $memory = 50)
    {
        if (empty($obj)) {
            return false;
        }

        return new static($obj, $memory);
    }

  /**
   * Converts the string to bytes
   *
   * @param string|int $amount
   *
   * @return int
   */
    public function convertToByte($amount): int
    {
        $value = (string)(int)$amount;
        $unit = strtolower(substr($amount, strlen($value)));

        if ($unit == "g" || $unit == "gb") {
            $value *= 1024 * 1024 * 1024;
        } elseif ($unit == "m" || $unit == "mb") {
            $value *= 1024 * 1024;
        } elseif ($unit == "k" || $unit == "kb") {
            $value *= 1024;
        }

        return $value;
    }

  /**
   * Memory leak protection
   *
   * @param int $mem
   */
    private function setMemoryLimit(int $mem): void
    {
      // Its check only once for performance.
        if ($this->memoryLimit > 0) {
            return;
        }

        $mbToByte = 1024 * 1024;
        $default = 50 * $mbToByte;

        $mem *= $mbToByte;

        $iniGetMem = ini_get('memory_limit') ?
        $this->convertToByte(ini_get('memory_limit')) : 0;

        if (empty($iniGetMem)) {
            $mem = $default;
        } elseif ($mem > $iniGetMem) {
            $mem = $iniGetMem;
        }

        $this->memoryLimit = $mem;
    }

  /**
   * Registers regex type
   *
   * @param string $key type key name
   * @param string $regex regex pattern
   */
    public function addRegexType(string $key, string $regex): void
    {
        if (!empty($key) && !empty($regex)) {
            $this->regexType[$key] = $regex;
        }
    }

  /**
   * Checks if the value is valid or not
   *
   * @param string $path requested path
   * @param string $type pre-defined validator type
   * @param bool $required field is required?
   * @param string $selfRegex self defined regex text
   */
    public function validator(string $path, string $type, $required = false, $selfRegex = ""): void
    {
        if (isset($this->regexType[$type])) {
            $regex = $this->regexType[$type];
        } elseif (!empty($selfRegex)) {
            $regex = $selfRegex;
        }

        if (!empty($regex)) {
            $this->validate($path, $regex, $required);
        }
    }

  /**
   * Validates the object path
   *
   * @param string $path
   * @param string $regex
   * @param bool $required
   *
   * @return bool
   *
   * @throws UnexpectedValueException
   */
    private function validate(string $path, string $regex, bool $required): bool
    {
        $result = true;
        $values = $this->getObj($path);

        if (!$this->isPathExists($path)) {
            $this->setAllPaths($this->validationErrors, $path, false);
        }

        if (!$required && !$values) {
            return true;
        }

        if (is_string($values) && !empty($values)) {
            $result = preg_match($regex, $values);

            if ($result === 0 || ($required && empty($values))) {
                $this->setAllPaths($this->validationErrors, $path, false);
                return false;
            } elseif ($result === 1) {
                return true;
            } elseif ($result === false) {
                throw new UnexpectedValueException("StrObj Error: Validation error!");
            }

            return $result;
        }

        $values = Collection::instance($values);

        while ($values->valid()) {
            $result = $result && $this->validate($values->key(), $regex, $required);
            $values->next();
        }

        if (!$result) {
            $this->setAllPaths($this->validationErrors, $path, false);
        }

        return $result;
    }

  /**
   * Checks whether the value which is in the desired path
   *  and added to the control list is valid or not
   *
   * @param string $path requested path
   *
   * @return bool
   */
    public function isValid(?string $path): bool
    {
        return $this->isPathExists($path) && !isset($this->validationErrors[$path]);
    }

  /**
   * Sets the value to the all parent paths.
   *
   * @param array $data
   * @param string $path
   * @param mixed $value
   */
    public function setAllPaths(&$data, $path, $value): void
    {
        $pathArray = explode('/', $path);

        if (!is_array($pathArray)) {
            return;
        }

        $totalPath = [];

        foreach ($pathArray as $pathPart) {
            $totalPath[] = $pathPart;

            if (is_array($totalPath) && !empty($totalPath)) {
                $newPath = implode('/', $totalPath);
                $data[$newPath] = $value;
            }
        }
    }

  /**
   * Saves the value to cache for performance
   *
   * @param string $path requested path
   * @param mixed $obj
   */
    private function saveStoredValue(string $path, $obj): void
    {
        $this->paths[$path] = $obj;
    }

  /**
   * Gets the stored value for performance. This function is used by get method.
   *
   * @param string $path requested path
   *
   * @return mixed
   */
    private function getStoredValue(string $path)
    {
        return $this->paths[$path];
    }

  /**
   * Checks whether the object path exists or not
   *
   * @param string $path
   *
   * @return bool
   */
    public function isPathExists(?string $path): bool
    {
        return array_key_exists($path, $this->paths);
    }

  /**
   * Performs an extensive search within the object.
   *
   * @param Collection $obj The object to be searched in
   * @param array $pathArray The array of the object path
   *
   * @return array
   */
    private function deepSearch(Collection $obj, array $pathArray): array
    {
        $results = [];
        $objKey = key($pathArray);

        while ($obj->valid()) {
            // new assignment for each branch
            $newPath = $pathArray;
            $newPath[$objKey] = $obj->key();
            $newPath = implode('/', $newPath);

            // get the object belonging to this branch
            $getObj = $this->getObj($newPath);

            if ($this->isPathExists($newPath)) {
                $results[$newPath] = $getObj;
            }

            $obj->next();
        }

        return $results;
    }

  /**
   * Returns the requested object with the given path.
   *
   * @param string $path The path of the object or array to be accessed
   *
   * @return mixed
   */
    private function getObj(string $path)
    {
        $obj = $this->obj;

        if (empty($path)) {
            return $obj;
        }

        if ($this->isPathExists($path)) {
            return $this->getStoredValue($path);
        }

        $pathArray = explode('/', $path);
        $currentPath = [];

        while (false !== $pathPart = current($pathArray)) {
            $obj = Collection::instance($obj);

            if ($pathPart === '*') {
                $obj = $this->deepSearch($obj, $pathArray);
                $this->saveStoredValue($path, $obj);
                return $obj;
            }

            $currentPath[] = $pathPart;
            $this->currentPath = implode('/', $currentPath);

            if ($obj->valid()) {
                if ($obj->offsetExists($pathPart)) {
                    $obj = $obj->offsetGet($pathPart);
                    $this->saveStoredValue($this->currentPath, $obj);
                }
            }

            next($pathArray);
        }

        return $obj;
    }

  /**
   * Searches the requested object with the given path.
   *
   * @param string $path The path of the object or array to be accessed
   *
   * @return bool|object Returns $this if query is exists
   *                     otherwise returns false
   *
   * @throws OverflowException
   */
    public function query(string $path)
    {
        if (memory_get_usage() > $this->memoryLimit) {
            throw new OverflowException(
                sprintf(
                    "StrObj Error: Allowed memory size of %s exhausted! (max: %s bytes)",
                    (int)(memory_get_usage() - $this->memoryLimit),
                    $this->memoryLimit
                )
            );
        }

        $this->results = $this->getObj($path);

        if (!empty($path) && !$this->isPathExists($path)) {
            return false;
        }

        return $this;
    }

  /**
   * Gets the value from the inside of the loaded object
   *  or returns the default value
   *
   * @param string $path requested object path like
   *               data/child_data instead of data->child_data
   * @param mixed  $default default value will return if value not exists
   *
   * @return mixed
   */
    public function get(string $path, $default = false)
    {
        $results = $this->query($path);

        if ($results instanceof self) {
            return $results->getResults();
        }
        return $default;
    }

  /**
   * Returns query results
   *
   * @return mixed
   */
    public function getResults()
    {
        return $this->results;
    }
}
