<?php

/*
* This file is part of the StrObj package.
*
* (c) Uğur Biçer <contact@codeplus.dev>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

declare(strict_types=1);

namespace StrObj\Helpers;

use InvalidArgumentException;

trait Adapters
{
    /**
     * Converts the string to bytes
     *
     * @param string|int  $amount
     *
     * @return int
     */
    public function convertToByte($amount): int
    {
        if (!is_int($amount) && !is_string($amount)) {
            throw new InvalidArgumentException('Memory amounts must be integers or strings.');
        }

        $amount = trim((string) $amount);

        if ($amount === '-1') {
            return -1;
        }

        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([kmgt]?)(?:b)?$/i', $amount, $matches)) {
            throw new InvalidArgumentException('Invalid memory amount.');
        }

        $units = ['' => 0, 'k' => 1, 'm' => 2, 'g' => 3, 't' => 4];
        $bytes = (float) $matches[1] * pow(1024, $units[strtolower($matches[2])]);

        if ($bytes >= PHP_INT_MAX) {
            throw new InvalidArgumentException('Memory amount exceeds the integer range.');
        }

        return (int) $bytes;
    }

    /**
     * Converts bytes to string
     *
     * @param int $bytes
     *
     * @return string
     */
    public function convertToString(int $bytes): string
    {
        if ($bytes < 0) {
            throw new InvalidArgumentException('Byte count cannot be negative.');
        }

        if ($bytes === 0) {
            return '0 b';
        }

        $units = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        $level = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return sprintf('%d %s', $bytes / pow(1024, $level), $units[$level]);
    }

    /**
     * Casts the value to the given type
     *
     * @param mixed   $value
     * @param string  $type
     *
     * @return mixed
     */
    public function castType($value, string $type)
    {
        return $this->castValue($value, $type, false);
    }

    /**
     * Casts a value and rejects unknown types or invalid JSON.
     *
     * @param mixed $value Value to cast.
     * @param string $type Supported cast name.
     * @return mixed
     */
    public function castTypeStrict($value, string $type)
    {
        return $this->castValue($value, $type, true);
    }

    /** @return mixed Shared cast implementation for both compatibility profiles. */
    private function castValue($value, string $type, bool $strict)
    {

        if ($type === 'int') {
            return (int) $value;
        }

        if ($type === 'float') {
            return (float) $value;
        }

        if ($type === 'bool') {
            return (bool) $value;
        }

        if ($type === 'string') {
            return (string) $value;
        }

        if ($type === 'array') {
            return (array) $value;
        }

        if ($type === 'object') {
            return (object) $value;
        }

        if ($type === 'json') {
            return json_decode($value, false, 512, $strict ? JSON_THROW_ON_ERROR : 0);
        }

        if ($strict) {
            throw new InvalidArgumentException('Unsupported filter type: ' . $type);
        }

        return $value;
    }
}
