<?php
/**
 * It enables you to access your objects readily without any problem and in a safe manner.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package strobj
 * @license GPLv2
 * @author Uğur Biçer <info@ugurbicer.com.tr>
 * @version 0.5.0
 */

namespace StrObj;

class StringObjects
{
	protected $obj = null;

	protected $cache = [];

	protected $sanitize_errors = [];

	protected $regex_type = [];

	protected static $memory_limit = 0;



	/**
	 * Loads the object to be used
	 * 
	 * @param object $obj The object to use
	 * @param int $memory The memory limit
	 */
	public function __construct($obj, int $memory)
	{
		$this->obj = $obj;

		self::_setmemory($memory);
	}



	/**
	 * Correct way to be used
	 * 
	 * @param object $obj The object to use
	 * @param int $memory The memory limit
	 */
	public static function instance($obj, int $memory = 50)
	{
		if (empty($obj) || ! is_object($obj)) {
			return false;
		}

		return new static($obj, $memory);
	}



	private static function _setmemory(int $mem)
	{
		// Its check only once for performance.
		if (self::$memory_limit > 0) {
			return;
		}

		$toMb = 1024 * 1024;
		$default = 50 * $toMb;
		$mem *= $toMb;
		$ini_get_mem = ini_get('memory_limit') ? ini_get('memory_limit') : 0;
		$ini_get_mem = intval($ini_get_mem) * $toMb;

		if (empty($ini_get_mem)) {
			$mem = $default * $toMb;
		} else if ($mem > $ini_get_mem) {
			$mem = $ini_get_mem;
		}

		self::$memory_limit = $mem;
	}



	/**
	 * Registers regex type
	 * 
	 * @param string $key type key name
	 * @param string $regex regex pattern
	 */
	public function addRegexType(string $key, string $regex)
	{
		if (! empty($key) && ! empty($regex)) {
			$this->regex_type[$key] = $regex;
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
	public function validator(string $path, string $type, bool $required = true, string $self_regex = "")
	{
		$values = $this->get($path);

		if (! $required && empty($values)) return;

		if (isset($this->regex_type[$type])) {
			$regex = $this->regex_type[$type];
		} else if (! empty($self_regex)) {
			$regex = $self_regex;
		}

		if (is_object($values)) {
			return;
		}

		if (! empty($regex)) {
			if (! is_array($values)) {
				$values = [$values];
			}

			$replace = substr_count($path, '*') > 0 ? true : false;

			foreach ($values as $key => $value) {
				$result = \preg_match($regex, $value);

				if ($result === 0) {
					$this->sanitize_errors[$path] = true;

					if ($replace) {
						$new_path = str_replace('*', $key, $path);
						$this->sanitize_errors[$new_path] = true;
					}
				} else if ($result === FALSE) {
					// ERROR
				}
			}
		}
	}



	/**
	 * Checks whether the value which is in the desired path and added to the control list is valid or not
	 * 
	 * @param string $path requested path
	 * 
	 * @return bool
	 */
	public function isValid(string $path)
	{
		$result = isset($this->sanitize_errors[$path]) && $this->sanitize_errors[$path] ? false : true;

		return $result;
	}



	/**
	 * Saves the value to cache for performance
	 * 
	 * @param string $path requested path
	 * @param mixed $obj
	 */
	public function saveCache(string $path, $obj)
	{
		$this->cache[$path] = $obj;
	}



	/**
	 * Gets the cached value for performance. This function is used by get method.
	 * 
	 * @param string $path requested path
	 * 
	 * @return null|string|object
	 */
	private function _getCache(string $path)
	{
		return isset($this->cache[$path]) ? $this->cache[$path] : null;
	}



	private function _get($obj, string $path)
	{
		if (isset($obj->{$path})) {
			$obj = $obj->{$path};
		} else if (isset($obj[$path])) {
			$obj = $obj[$path];
		}

		if (! empty($obj)) {
			return $obj;
		}

		return false;
	}



	private function _deepSearch($obj, string $key)
	{
		$result = [];

		foreach ($obj as $obj_key => $obj_item) {
			$result_item = $this->_get($obj_item, $key);

			if (! $result_item) continue;

			$result[$obj_key] = $result_item;
		}

		if (empty($result)) {
			return false;
		}

		return $result;
	}



	/**
	 * Gets the value from the inside of the loaded object or returns the default value
	 * 
	 * @param string $path requested object path like data/child_data instead of data->child_data
	 * @param mixed $default default value will return if value not exists
	 * 
	 * @return object|string 
	 */
	public function get(string $path, $default = false)
	{
		$cache_obj = $this->_getCache($path);

		if (! empty($cache_obj)) return $cache_obj;

		if (memory_get_usage() > self::$memory_limit) {
			// TODO: error information
			return $default;
		}

		if (empty($this->obj) || empty($path)) return false;

		$obj = $this->obj;

		$str_exp	= explode('/', $path);

		foreach ($str_exp as $key => $exp_path) {
			if ($exp_path === '*') {
				$result = $this->_deepSearch($obj, $str_exp[$key + 1]);

				return $result ? $result : $default;
			}

			$obj = $this->_get($obj, $exp_path);
		}

		if (empty($obj)) {
			return $default;
		}

		$this->saveCache($path, $obj);

		return $obj;
	}
}
