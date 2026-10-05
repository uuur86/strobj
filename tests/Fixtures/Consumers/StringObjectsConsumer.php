<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures\Consumers;

use StrObj\StringObjects;

/**
 * Overrides every recorded v2.1 method of {@see StringObjects} with its recorded signature.
 * PHP checks the signatures when it loads this class; each override records its call and delegates.
 */
final class StringObjectsConsumer extends StringObjects
{
    use RecordsCalls;

    /** {@inheritdoc} */
    public function __construct(object $obj, array $options = [])
    {
        self::record(__FUNCTION__);
        parent::__construct($obj, $options);
    }

    /** {@inheritdoc} */
    public static function instance($data, array $options = [])
    {
        self::record(__FUNCTION__);

        return parent::instance($data, $options);
    }

    /** {@inheritdoc} */
    public function get(?string $path = '', $default = false)
    {
        self::record(__FUNCTION__);

        return parent::get($path, $default);
    }

    /** {@inheritdoc} */
    public function set(string $path, $value): void
    {
        self::record(__FUNCTION__);
        parent::set($path, $value);
    }

    /** {@inheritdoc} */
    public function has(string $path): bool
    {
        self::record(__FUNCTION__);

        return parent::has($path);
    }

    /** {@inheritdoc} */
    public function toJson(): string
    {
        self::record(__FUNCTION__);

        return parent::toJson();
    }

    /** {@inheritdoc} */
    public function toArray(): array
    {
        self::record(__FUNCTION__);

        return parent::toArray();
    }

    /** {@inheritdoc} */
    public function isValid(string $path = ''): bool
    {
        self::record(__FUNCTION__);

        return parent::isValid($path);
    }

    /** {@inheritdoc} */
    public function setMemoryLimit(int $memory): void
    {
        self::record(__FUNCTION__);
        parent::setMemoryLimit($memory);
    }
}
