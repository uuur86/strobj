<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures\Consumers;

use StrObj\Data\DataObject;
use StrObj\Data\DataPath;

/**
 * Overrides every recorded v2.1 method of {@see DataObject} with its recorded signature.
 * PHP checks the signatures when it loads this class; each override records its call and delegates.
 */
final class DataObjectConsumer extends DataObject
{
    use RecordsCalls;

    /** {@inheritdoc} */
    public function __construct($data)
    {
        self::record(__FUNCTION__);
        parent::__construct($data);
    }

    /** {@inheritdoc} */
    public function pathInit(string $path): DataPath
    {
        self::record(__FUNCTION__);

        return parent::pathInit($path);
    }

    /** {@inheritdoc} */
    public function getCurrentPath(): string
    {
        self::record(__FUNCTION__);

        return parent::getCurrentPath();
    }

    /** {@inheritdoc} */
    public function cache(string $path, $value): void
    {
        self::record(__FUNCTION__);
        parent::cache($path, $value);
    }

    /** {@inheritdoc} */
    public function get(string $path)
    {
        self::record(__FUNCTION__);

        return parent::get($path);
    }

    /** {@inheritdoc} */
    public function query(?string $path = null)
    {
        self::record(__FUNCTION__);

        return parent::query($path);
    }

    /** {@inheritdoc} */
    public function set(string $path, $value): void
    {
        self::record(__FUNCTION__);
        parent::set($path, $value);
    }

    /** {@inheritdoc} */
    public function offsetSet($index, $val): void
    {
        self::record(__FUNCTION__);
        parent::offsetSet($index, $val);
    }

    /** {@inheritdoc} */
    public function getCols(string $colname): array
    {
        self::record(__FUNCTION__);

        return parent::getCols($colname);
    }

    /** {@inheritdoc} */
    public function jsonSerialize(): array
    {
        self::record(__FUNCTION__);

        return parent::jsonSerialize();
    }

    /** {@inheritdoc} */
    public function toArray(): array
    {
        self::record(__FUNCTION__);

        return parent::toArray();
    }

    /** {@inheritdoc} */
    public function findKey(string $key): bool
    {
        self::record(__FUNCTION__);

        return parent::findKey($key);
    }

    /** {@inheritdoc} */
    public function key(): string
    {
        self::record(__FUNCTION__);

        return parent::key();
    }

    /** {@inheritdoc} */
    public function has(string $path): bool
    {
        self::record(__FUNCTION__);

        return parent::has($path);
    }
}
