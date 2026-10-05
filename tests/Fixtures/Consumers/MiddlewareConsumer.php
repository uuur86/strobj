<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures\Consumers;

use StrObj\Middleware;

/**
 * Overrides every recorded v2.1 method of {@see Middleware} with its recorded signature.
 * PHP checks the signatures when it loads this class; each override records its call and delegates.
 */
final class MiddlewareConsumer extends Middleware
{
    use RecordsCalls;

    /** {@inheritdoc} */
    public function __construct(array $options = [])
    {
        self::record(__FUNCTION__);
        parent::__construct($options);
    }

    /** {@inheritdoc} */
    public function set(string $name, $value): void
    {
        self::record(__FUNCTION__);
        parent::set($name, $value);
    }

    /** {@inheritdoc} */
    public function get(string $name)
    {
        self::record(__FUNCTION__);

        return parent::get($name);
    }

    /** {@inheritdoc} */
    public function setMemoryLimit(int $memory): void
    {
        self::record(__FUNCTION__);
        parent::setMemoryLimit($memory);
    }

    /** {@inheritdoc} */
    public function memoryLeakProtection(): void
    {
        self::record(__FUNCTION__);
        parent::memoryLeakProtection();
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
}
