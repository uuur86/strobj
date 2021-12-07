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
 * @version 1.0.0
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
use function intval;
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
  private $_obj = null;

  /**
   * Latest query path info
   * 
   * @var string
   */
  private $_current_path = null;

  /**
   * Stored object values
   * 
   * @var array
   */
  private $_paths = [];

  /**
   * Query results
   * 
   * @var array
   */
  private $_results = [];

  /**
   * The object paths about validation errors
   * 
   * @var array
   */
  private $_validation_errors = [];

  /**
   * User defined regex templates
   * 
   * @var array
   */
  private $_regex_type = [];

  /**
   * Memory limit in bytes
   * 
   * @var int
   */
  private $_memory_limit = 0;

  /**
   * Constructor
   * 
   * @param object|array $obj The object to use
   * @param int $memory The memory limit
   */
  public function __construct($obj, int $memory)
  {
    $this->_obj = $obj;

    $this->_setMemoryLimit($memory);
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
    $value = (string)intval($amount);
    $unit = strtolower(substr($amount, strlen($value)));

    if ($unit == "g" || $unit == "gb") {
      $value *= 1024 * 1024 * 1024;
    } else if ($unit == "m" || $unit == "mb") {
      $value *= 1024 * 1024;
    } else if ($unit == "k" || $unit == "kb") {
      $value *= 1024;
    }

    return $value;
  }

  /**
   * Memory leak protection
   * 
   * @param int $mem
   */
  private function _setMemoryLimit(int $mem): void
  {
    // Its check only once for performance.
    if ($this->_memory_limit > 0) {
      return;
    }

    $mbToByte = 1024 * 1024;
    $default = 50 * $mbToByte;

    $mem *= $mbToByte;

    $ini_get_mem = ini_get('memory_limit') ?
      $this->convertToByte(ini_get('memory_limit')) : 0;

    if (empty($ini_get_mem)) {
      $mem = $default;
    } else if ($mem > $ini_get_mem) {
      $mem = $ini_get_mem;
    }

    $this->_memory_limit = $mem;
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
      $this->_regex_type[$key] = $regex;
    }
  }

  /**
   * Checks if the value is valid or not
   * 
   * @param string $path requested path
   * @param string $type pre-defined validator type
   * @param bool $required field is required?
   * @param string $self_regex self defined regex text
   */
  public function validator(string $path, string $type, bool $required = false, string $self_regex = ""): void
  {
    if (isset($this->_regex_type[$type])) {
      $regex = $this->_regex_type[$type];
    } else if (!empty($self_regex)) {
      $regex = $self_regex;
    }

    if (!empty($regex)) {
      $this->_validate($path, $regex, $required);
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
  private function _validate(string $path, string $regex, bool $required): bool
  {
    $result = true;

    $values = $this->_get($path);

    if (!$this->isPathExists($path)) {
      $this->setAllPaths($this->_validation_errors, $path, false);
    }

    if (!$required && !$values) return true;

    if (is_string($values) && !empty($values)) {
      $result = preg_match($regex, $values);

      if ($result === 0 || ($required && empty($values))) {
        $this->setAllPaths($this->_validation_errors, $path, false);
        return false;
      } else if ($result === 1) {
        return true;
      } else if ($result === FALSE) {
        throw new UnexpectedValueException("StrObj Error: Validation error!");
      }

      return $result;
    }

    $values = Collection::instance($values);

    while ($values->valid()) {
      $result = $result && $this->_validate($values->key(), $regex, $required);
      $values->next();
    }

    if (!$result) {
      $this->setAllPaths($this->_validation_errors, $path, false);
    }

    return $result;
  }

  /**
   * Checks whether the value which is in the desired path and added to the control list is valid or not
   * 
   * @param string $path requested path
   * 
   * @return bool
   */
  public function isValid(?string $path): bool
  {
    return $this->isPathExists($path) && !isset($this->_validation_errors[$path]);
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
    $path_array = explode('/', $path);

    if (!is_array($path_array)) return;

    $total_path = [];

    foreach ($path_array as $path_) {
      $total_path[] = $path_;

      if (is_array($total_path) && !empty($total_path)) {
        $new_path = implode('/', $total_path);
        $data[$new_path] = $value;
      }
    }
  }

  /**
   * Saves the value to cache for performance
   * 
   * @param string $path requested path
   * @param mixed $obj
   */
  private function _saveStoredValue(string $path, $obj): void
  {
    $this->_paths[$path] = $obj;
  }

  /**
   * Gets the stored value for performance. This function is used by get method.
   * 
   * @param string $path requested path
   * 
   * @return mixed
   */
  private function _getStoredValue(string $path)
  {
    return $this->_paths[$path];
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
    return array_key_exists($path, $this->_paths);
  }

  /**
   * Performs an extensive search within the object.
   * 
   * @param Collection $obj The object to be searched in
   * @param array $path_array The array of the object path
   * 
   * @return array
   */
  private function _deepSearch(Collection $obj, array $path_array): array
  {
    $results = [];
    $obj_key = key($path_array);

    while ($obj->valid()) {
      // new assignment for each branch
      $new_path = $path_array;
      $new_path[$obj_key] = $obj->key();
      $new_path = implode('/', $new_path);

      // get the object belonging to this branch
      $get_obj = $this->_get($new_path);

      if ($this->isPathExists($new_path)) {
        $results[$new_path] = $get_obj;
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
  private function _get(?string $path)
  {
    if ($this->isPathExists($path)) {
      return $this->_getStoredValue($path);
    }

    $obj = $this->_obj;

    $path_array = explode('/', $path);
    $current_path = [];

    while (false !== $path_part = current($path_array)) {
      $obj = Collection::instance($obj);

      if ($path_part === '*') {
        $obj = $this->_deepSearch($obj, $path_array);
        $this->_saveStoredValue($path, $obj);
        return $obj;
      }

      $current_path[] = $path_part;
      $this->_current_path = implode('/', $current_path);

      if ($obj->valid()) {
        if ($obj->offsetExists($path_part)) {
          $obj = $obj->offsetGet($path_part);
          $this->_saveStoredValue($this->_current_path, $obj);
        }
      }

      next($path_array);
    }

    return $obj;
  }

  /**
   * Searches the requested object with the given path.
   * 
   * @param string $path The path of the object or array to be accessed
   * 
   * @return bool|object Returns $this if query is exists otherwise returns false
   * 
   * @throws OverflowException
   */
  public function query($path)
  {
    if (memory_get_usage() > $this->_memory_limit) {
      throw new OverflowException(
        sprintf(
          "StrObj Error: Allowed memory size of %s exhausted! (max: %s bytes)",
          intval(memory_get_usage() - $this->_memory_limit),
          $this->_memory_limit
        )
      );
    }

    if (empty($path)) return false;

    $this->_results = $this->_get($path);

    return $this;
  }

  /**
   * Gets the value from the inside of the loaded object or returns the default value
   * 
   * @param string $path requested object path like data/child_data instead of data->child_data
   * @param mixed $default default value will return if value not exists
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
    return $this->_results;
  }
}
