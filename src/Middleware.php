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

namespace StrObj;

use OverflowException;
use StrObj\Helpers\Adapters;

/**
 * Middleware class
 */
class Middleware
{
    use Adapters;

    /**
     * Memory limit in bytes
     *
     * @var array
     */
    private array $_options = [];

    /**
     * __construct function
     *
     * @param array $options only memory_limit for now
     */
    public function __construct(array $options = [])
    {
        $this->_options = $options;
    }

    /**
     * Set middleware options
     *
     * @param string $name  The key parameter that uses in option key-value pair
     * @param mixed  $value The value parameter that uses in option key-value pair
     *
     * @return void
     */
    public function set(string $name, $value): void
    {
        $this->_options[$name] = $value;
    }

    /**
     * Get middleware options
     *
     * @param string $name The key name that uses for getting the value
     *
     * @return mixed
     */
    public function get(string $name)
    {
        return $this->_options[$name] ?? null;
    }

    /**
     * Memory leak protection
     *
     * @param int $memory memory limit in Mb
     *
     * @return void
     */
    public function setMemoryLimit(int $memory): void
    {
        // Its check only once for performance.
        if ($this->get('memory_limit') > 0) {
            return;
        }

        $mbToByte = 1024 * 1024;
        $default = 50 * $mbToByte;

        $memory *= $mbToByte;

        $iniGetMem = ini_get('memory_limit') ?
            $this->convertToByte(ini_get('memory_limit')) : 0;

        if (empty($iniGetMem)) {
            $memory = $default;
        } elseif ($memory > $iniGetMem) {
            $memory = $iniGetMem;
        }

        $this->set('memory_limit', $memory);
    }

    /**
     * Memory leak protection
     *
     * @return void
     */
    public function memoryLeakProtection(): void
    {
        $memoryUsage = memory_get_usage();

        if ($memoryUsage > $this->get('memory_limit')) {
            throw new OverflowException(
                sprintf(
                    'Memory limit exceeded. Memory usage: %s',
                    $this->convertToString($memoryUsage)
                )
            );
        }
    }
}
