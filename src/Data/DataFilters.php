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

namespace StrObj\Data;

use Closure;
use StrObj\Helpers\Adapters;
use StrObj\Helpers\DataParsers;

class DataFilters
{
    /**
     * Adapter trait
     */
    use Adapters;

    /**
     * DataParsers trait
     */
    use DataParsers;

    /**
     * @var array
     */
    private array $options = [];

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;
    }

    /**
     * Filters output data
     *
     * @param string $path
     * @param mixed $data
     *
     * @return mixed
     */
    public function filter(string $path, $data)
    {
        if (!isset($this->options[$path])) {
            // TODO: #19 find inclusive path
            // $path = $this->findInclusivePaths($path, $this->options);

            return $data;
        }

        $filters = $this->options[$path];

        if (is_array($data)) {
            return array_filter($data, function ($item) use ($filters) {
                return $this->filterValue($item, $filters);
            });
        }

        return $this->filterValue($data, $filters);
    }

    /**
     * Filter data on a specific value
     *
     * @param mixed $value
     * @param array $filters
     *
     * @return mixed
     */
    private function filterValue($value, $filters)
    {
        $filterType = $filters['type'] ?? 'string';
        $filterArgs = $filters['args'] ?? [];
        $filterCallback = $filters['callback'] ?? null;

        $value = $this->castType($value, $filterType);

        if (is_callable($filterCallback) && $filterCallback instanceof Closure) {
            $args = [$value];

            if (is_array($filterArgs)) {
                $args = array_merge($args, $filterArgs);
            } else {
                $args[] = $filterArgs;
            }

            return call_user_func_array($filterCallback, $args);
        }

        return $value;
    }
}
