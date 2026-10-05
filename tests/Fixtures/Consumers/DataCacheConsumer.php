<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures\Consumers;

use StrObj\Data\DataCache;

/**
 * Overrides every recorded v2.1 method of {@see DataCache} with its recorded signature.
 * PHP checks the signatures when it loads this class; each override records its call and delegates.
 */
final class DataCacheConsumer extends DataCache
{
    use RecordsCalls;

    /** {@inheritdoc} */
    public function save(string $path, $data): void
    {
        self::record(__FUNCTION__);
        parent::save($path, $data);
    }

    /** {@inheritdoc} */
    public function clear(string $path): void
    {
        self::record(__FUNCTION__);
        parent::clear($path);
    }

    /** {@inheritdoc} */
    public function get(string $path)
    {
        self::record(__FUNCTION__);

        return parent::get($path);
    }

    /** {@inheritdoc} */
    public function setPath(string $path, $data): void
    {
        self::record(__FUNCTION__);
        parent::setPath($path, $data);
    }

    /** {@inheritdoc} */
    protected function addPaths(array $paths): void
    {
        self::record(__FUNCTION__);
        parent::addPaths($paths);
    }

    /** {@inheritdoc} */
    public function isCached(string $path)
    {
        self::record(__FUNCTION__);

        return parent::isCached($path);
    }
}
