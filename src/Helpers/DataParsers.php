<?php

namespace StrObj\Helpers;

use Closure;

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

    /**
     * Find relative paths
     *
     * @param string  $path     The path to find
     * @param array   $data     The data to search for paths
     * @param Closure $closure  The closure to run on each path
     *
     * @return array
     */
    public function findPaths(string $path, array $data, ?Closure $closure = null): array
    {
        if (substr_count($path, "*") === 0) {
            return [$path => $data];
        }

        $paths = [];

        foreach ($data as $key => $val) {
            if (substr_count($path, "*") > 0) {
                $path_ = substr_replace($path, $key, strpos($path, "*"), 1);

                if ($closure) {
                    $val = $closure($path_, $val);
                }

                $paths[$path_] = $val;
            }
        }

        return $paths;
    }
}
