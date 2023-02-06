<?php

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
        $value = (int)$amount;
        $unit = strtolower(substr($amount, strlen((string)$value)));

        if ($unit == "g" || $unit == "gb") {
            $value *= 1024 * 1024 * 1024;
        } elseif ($unit == "m" || $unit == "mb") {
            $value *= 1024 * 1024;
        } elseif ($unit == "k" || $unit == "kb") {
            $value *= 1024;
        }

        return $value;
    }
}
