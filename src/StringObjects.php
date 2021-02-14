<?php
/**
 * It enables you to access your objects readily without any problem and in a safe manner.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package strobj
 * @license GPLv2
 * @author Uğur Biçer <uuur86@yandex.com>
 * @version 0.4
 */

namespace StrObj;

use stdClass;

class StringObjects
{
	protected $obj = null;

	protected $cache = [];

	protected $sanitize_errors = [];


	/**
	 * Loads the object to use
	 * 
	 * @param object $obj The object to use
	 */
	public function __construct($obj) {
		$this->obj = $obj;
	}



	/**
	 * Checks value if it valid or not
	 * 
	 * @param string $str requested path
	 * @param string $type pre-defined control type(not ready)
	 * @param string $self_regex self defined regex text
	 */
	public function control($str, $type, $self_regex = "")
	{
		$value = $this->get($str);

		if (! empty($self_regex)) {
			$regex = $self_regex;
		}

		if (! empty($regex)) {
			$result = \preg_match($regex, $value);

			if ($result === 0) {
				$this->sanitize_errors[$str] = true;
			} else if ($result === FALSE) {
				// ERROR
			}
		}
	}



	/**
	 * Checks the value whether it valid or not which is added in control list.
	 * 
	 * @param string $str requested path
	 * 
	 * @return bool
	 */
	public function isValid($str)
	{
		return isset($this->sanitize_errors[$str]) && $this->sanitize_errors[$str] ? false : true;
	}



	/**
	 * Saves value to cache for performance
	 * 
	 * @param string $str requested path
	 * @param mixed $obj
	 */
	public function saveCache($str, $obj)
	{
		$this->cache[$str] = $obj;
	}



	/**
	 * Gets cached value for performance. This function is using by get function.
	 * 
	 * @param string $str requested path
	 * 
	 * @return null|string|object
	 */
	private function _getCache($str)
	{
		return isset($this->cache[$str]) ? $this->cache[$str] : null;
	}



	/**
	 * Gets the value from inside of loaded object or returns default value
	 * 
	 * @param string $str requested object path like data/child_data for data->child_data
	 * @param mixed $default default value will return if value not exists
	 * 
	 * @return object|string 
	 */
	public function get($str, $default = false) {

		if (empty($this->obj)) return $default;

		$cache_obj = $this->_getCache($str);

		if (! empty($cache_obj)) return $cache_obj;

		$str_exp	= explode('/', $str);
		$obj		= $this->obj;

		foreach ($str_exp as $obj_name) {

			if (! isset( $obj->{$obj_name})) return $default;

			$obj = $obj->{$obj_name};
		}

		$this->saveCache($str, $obj);

		return $obj;
	}
}
