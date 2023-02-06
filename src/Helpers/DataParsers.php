<?php

namespace StrObj\Helpers;

trait DataParsers
{
    public function parsePath(string $path)
    {
        $path_arr = preg_split('#[\/]+#', $path);

        if ($path_arr === false) {
            return;
        }

        $path_arr = array_filter($path_arr, function ($value) {
            return preg_match('#[^\/]+#siu', $value);
        });

        return $path_arr;
    }
}
