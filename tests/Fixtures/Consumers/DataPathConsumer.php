<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures\Consumers;

use Closure;
use StrObj\Data\DataPath;

/**
 * Overrides every recorded v2.1 method of {@see DataPath} with its recorded signature.
 * PHP checks the signatures when it loads this class; each override records its call and delegates.
 */
final class DataPathConsumer extends DataPath
{
    use RecordsCalls;

    /** {@inheritdoc} */
    public function __construct(string $path)
    {
        self::record(__FUNCTION__);
        parent::__construct($path);
    }

    /** {@inheritdoc} */
    public static function init(string $path)
    {
        self::record(__FUNCTION__);

        return parent::init($path);
    }

    /** {@inheritdoc} */
    public function getRaw(): string
    {
        self::record(__FUNCTION__);

        return parent::getRaw();
    }

    /** {@inheritdoc} */
    public function getArray(): array
    {
        self::record(__FUNCTION__);

        return parent::getArray();
    }

    /** {@inheritdoc} */
    public function getBranches()
    {
        self::record(__FUNCTION__);

        return parent::getBranches();
    }

    /** {@inheritdoc} */
    public function exists(string $path): bool
    {
        self::record(__FUNCTION__);

        return parent::exists($path);
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
