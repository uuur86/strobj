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

namespace StrObj\Helpers;

use Closure;
use InvalidArgumentException;

trait DataParsers
{
    /**
     * Parses slash-separated segments; zero and Unicode keys are preserved.
     *
     * @param string $path Path to parse.
     *
     * @return string[]
     * @throws \InvalidArgumentException If the path exceeds 512 segments.
     */
    public function parsePath(string $path)
    {
        $segments = array_values(array_filter(explode('/', $path), static function (string $segment): bool {
            return $segment !== '';
        }));

        if (count($segments) > 512) {
            throw new InvalidArgumentException('A path cannot exceed 512 segments.');
        }

        return $segments;
    }

    /**
     * Normalizes repeated, leading and trailing separators.
     *
     * @param string $path Path to normalize.
     *
     * @return string
     */
    public function normalizePath(string $path): string
    {
        return implode('/', $this->parsePath($path));
    }

    /**
     * Matches complete segments; each wildcard matches one segment.
     *
     * @param string $pattern Literal/wildcard pattern.
     * @param string $path    Concrete path to compare.
     *
     * @return bool
     */
    public function matchesPath(string $pattern, string $path): bool
    {
        $patternSegments = $this->parsePath($pattern);
        $pathSegments = $this->parsePath($path);

        if (count($patternSegments) !== count($pathSegments)) {
            return false;
        }

        foreach ($patternSegments as $index => $segment) {
            if ($segment !== '*' && $segment !== $pathSegments[$index]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Expands wildcard paths from a projected value array, preserving its keys.
     *
     * @param string  $path     The path to find
     * @param array        $data    Projected values for each wildcard level.
     * @param Closure|null $closure Optional callback receiving concrete path and value.
     *
     * @return array
     */
    public function findPaths(string $path, array $data, ?Closure $closure = null): array
    {
        $position = strpos($path, '*');

        if ($position === false) {
            return [$path => $closure === null ? $data : $closure($path, $data)];
        }

        $paths = [];

        foreach ($data as $key => $value) {
            $concrete = substr_replace($path, (string) $key, $position, 1);

            if (strpos($concrete, '*') !== false) {
                if (is_array($value)) {
                    $paths = array_replace($paths, $this->findPaths($concrete, $value, $closure));
                }
            } else {
                $paths[$concrete] = $closure === null ? $value : $closure($concrete, $value);
            }
        }

        return $paths;
    }

    /**
     * Finds the matching option with the most literal path segments.
     * Equal specificity keeps the first configured option.
     *
     * @param string $path
     * @param array  $options
     *
     * @return string
     */
    protected function findInclusivePaths(string $path, array $options): string
    {
        return $this->selectMatchingPath($path, $options, true);
    }

    /**
     * Selects a matching pattern with either configuration order or literal specificity.
     *
     * @param string $path Concrete query path.
     * @param array $options Configured path patterns.
     * @param bool $preferSpecific Prefer the most literal matching pattern.
     * @return string
     */
    protected function selectMatchingPath(string $path, array $options, bool $preferSpecific): string
    {
        $best = '';
        $specificity = -1;

        foreach ($options as $optionPath => $value) {
            $optionPath = (string) $optionPath;

            if (!$this->matchesPath($optionPath, $path)) {
                continue;
            }

            if (!$preferSpecific) {
                return $optionPath;
            }

            $score = count(array_filter($this->parsePath($optionPath), static function (string $segment): bool {
                return $segment !== '*';
            }));

            if ($score > $specificity) {
                $best = $optionPath;
                $specificity = $score;
            }
        }

        return $best;
    }
}
