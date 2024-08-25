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

namespace StrObj\Helpers;

use Closure;

trait DataParsers
{
    public function parsePath(string $path)
    {
        $path_arr = preg_split('#[\/]+#', $path);

        if ($path_arr === false) {
            return [$path];
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

    /**
     * Finds the most inclusive path in the options array
     *
     * @param string $path
     * @param array  $options
     *
     * @return string
     */
    protected function findInclusivePaths(string $path, array $options): string
    {
        foreach ($options as $optionPath => $value) {
            $asterixIndex = 0;

            while ($asterixPos = strpos($optionPath, '*', $asterixIndex)) {
                $asterixIndex = $asterixPos + 1;
                $path = substr_replace($path, '*', $asterixPos, strpos($path, '/', $asterixPos) - $asterixPos);
            }

            if ($path === $optionPath) {
                return $optionPath;
            }
        }

        return '';
    }
}
