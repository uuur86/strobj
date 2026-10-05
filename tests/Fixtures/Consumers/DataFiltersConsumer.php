<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures\Consumers;

use Closure;
use StrObj\Data\DataFilters;

/**
 * Overrides every recorded v2.1 method of {@see DataFilters} with its recorded signature.
 * PHP checks the signatures when it loads this class; each override records its call and delegates.
 */
final class DataFiltersConsumer extends DataFilters
{
    use RecordsCalls;

    /** {@inheritdoc} */
    public function __construct(array $options)
    {
        self::record(__FUNCTION__);
        parent::__construct($options);
    }

    /** {@inheritdoc} */
    public function filter(string $path, $data)
    {
        self::record(__FUNCTION__);

        return parent::filter($path, $data);
    }

    /** {@inheritdoc} */
    public function convertToByte($amount): int
    {
        self::record(__FUNCTION__);

        return parent::convertToByte($amount);
    }

    /** {@inheritdoc} */
    public function convertToString(int $bytes): string
    {
        self::record(__FUNCTION__);

        return parent::convertToString($bytes);
    }

    /** {@inheritdoc} */
    public function castType($value, string $type)
    {
        self::record(__FUNCTION__);

        return parent::castType($value, $type);
    }

    /** {@inheritdoc} */
    public function parsePath(string $path)
    {
        self::record(__FUNCTION__);

        return parent::parsePath($path);
    }

    /** {@inheritdoc} */
    public function findPaths(string $path, array $data, ?Closure $closure = null): array
    {
        self::record(__FUNCTION__);

        return parent::findPaths($path, $data, $closure);
    }

    /** {@inheritdoc} */
    protected function findInclusivePaths(string $path, array $options): string
    {
        self::record(__FUNCTION__);

        return parent::findInclusivePaths($path, $options);
    }
}
