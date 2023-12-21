<?php

/*
 * This file is part of the StrObj package.
 *
 * (c) Uğur Biçer <contact@codeplus.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace StrObj\Helpers;

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
        $value = (int) $amount;
        $unit = strtolower(substr($amount, strlen((string) $value)));

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
     * Converts bytes to string
     *
     * @param int $bytes
     *
     * @return string
     */
    public function convertToString(int $bytes): string
    {
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        $level = floor(log($bytes, 1024));
        $bytes = $bytes / pow(1024, $level);
        return sprintf('%d %s', $bytes, $unit[$level]);
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
        switch ($type) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return (bool) $value;
            case 'string':
                return (string) $value;
            case 'array':
                return (array) $value;
            case 'object':
                return (object) $value;
            case 'json':
                return json_decode($value);
            default:
                return $value;
        }
    }
}
