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

use InvalidArgumentException;
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
    /** @var bool Whether memory limits require exact integer values. */
    private bool $consistent;

    /**
     * __construct function
     *
     * @param array $options memory_limit is bytes; omitted or -1 disables the local guard
     * @param bool $consistent Enable strict option checking.
     */
    public function __construct(array $options = [], bool $consistent = false)
    {
        $this->consistent = $consistent;

        foreach ($options as $name => $value) {
            $this->set((string) $name, $value);
        }
    }
    /** @return self Middleware with strict option checking. */
    public static function consistent(array $options = []): self
    {
        return new self($options, true);
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
        if ($name === 'memory_limit') {
            $bytes = $value;

            if (!$this->consistent && is_string($value)) {
                $bytes = $this->toNumber($value);
            }

            $validType = is_int($bytes) || (!$this->consistent && is_float($bytes) && is_finite($bytes));

            if (!$validType || ($bytes <= 0 && $bytes != -1)) {
                throw new InvalidArgumentException('memory_limit must be positive bytes or -1.');
            }
        }

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
     * Updates the local memory guard; -1 in php.ini means no global cap.
     *
     * @param int $memory memory limit in Mb
     *
     * @return void
     */
    public function setMemoryLimit(int $memory): void
    {
        if ($memory <= 0 || $memory > intdiv(PHP_INT_MAX, 1024 * 1024)) {
            throw new InvalidArgumentException('Memory limit must be a positive representable number of megabytes.');
        }

        $bytes = $memory * 1024 * 1024;
        $phpLimit = $this->convertToByte(ini_get('memory_limit'));

        if ($phpLimit > 0) {
            $bytes = min($bytes, $phpLimit);
        }

        $this->set('memory_limit', $bytes);
    }

    /**
     * Checks current memory use against the enabled local guard
     *
     * @return void
     */
    public function memoryLeakProtection(): void
    {
        $limit = $this->get('memory_limit');
        $limit = is_string($limit) ? $this->toNumber($limit) : $limit;

        if ($limit === null || $limit == -1) {
            return;
        }

        $usage = memory_get_usage();

        if ($usage > $limit) {
            throw new OverflowException(sprintf(
                'Memory limit exceeded. Memory usage: %s',
                $this->convertToString($usage)
            ));
        }
    }

    /**
     * Converts a numeric string, ignoring surrounding whitespace on every PHP version.
     * PHP 7.4 does not treat trailing whitespace as numeric; PHP 8 does.
     *
     * @param string $value Configured value.
     *
     * @return int|float|string The number, or the original string when it is not numeric.
     */
    private function toNumber(string $value)
    {
        $trimmed = trim($value);

        return is_numeric($trimmed) ? $trimmed + 0 : $value;
    }
}
