<?php

/*
* This file is part of the StrObj package.
*
* (c) Uğur Biçer <contact@fyndsoft.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

declare(strict_types=1);

namespace StrObj\Helpers;

use InvalidArgumentException;

/**
 * Conversion helpers shared by the library's components
 *
 * @internal These methods are implementation details and may change without notice.
 */
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

    /**
     * Shared cast implementation for both compatibility profiles.
     * Values that PHP cannot cast without an error or warning are returned unchanged
     * by the legacy profile and rejected by the strict profile.
     *
     * @param mixed  $value  Value to cast.
     * @param string $type   Supported cast name.
     * @param bool   $strict Reject unknown types, invalid JSON and impossible casts.
     *
     * @return mixed
     * @throws InvalidArgumentException In strict mode, for unsupported types or values.
     */
    private function castValue($value, string $type, bool $strict)
    {
        if (!$this->isCastable($value, $type, $strict)) {
            if ($strict) {
                throw new InvalidArgumentException(
                    sprintf('Cannot cast a value of type %s to %s.', gettype($value), $type)
                );
            }

            return $value;
        }

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
            if ($value === null) {
                return null;
            }

            return json_decode((string) $value, false, 512, $strict ? JSON_THROW_ON_ERROR : 0);
        }

        if ($strict) {
            throw new InvalidArgumentException('Unsupported filter type: ' . $type);
        }

        return $value;
    }

    /**
     * Reports whether a cast completes without a PHP error or warning.
     * Strict JSON casts accept strings only; legacy JSON casts also decode scalars and null.
     *
     * @param mixed  $value  Value to cast.
     * @param string $type   Cast name.
     * @param bool   $strict Whether the strict profile is active.
     *
     * @return bool
     */
    private function isCastable($value, string $type, bool $strict): bool
    {
        if ($type === 'int' || $type === 'float') {
            return !is_object($value);
        }

        if ($type === 'string') {
            return is_scalar($value) || $value === null || (is_object($value) && method_exists($value, '__toString'));
        }

        if ($type === 'json') {
            return is_string($value) || (!$strict && (is_scalar($value) || $value === null));
        }

        return true;
    }
}
