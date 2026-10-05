<?php

declare(strict_types=1);

namespace StrObj\Tests\Unit;

use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use StrObj\Middleware;

final class MiddlewareTest extends TestCase
{
    public function testDefaultsAndOptionsCanBeUpdated(): void
    {
        $middleware = Middleware::consistent(['custom' => 'first']);
        self::assertNull($middleware->get('missing'));
        self::assertSame('first', $middleware->get('custom'));
        $middleware->set('custom', 'second');
        self::assertSame('second', $middleware->get('custom'));
        $middleware->memoryLeakProtection();
        $middleware->set('memory_limit', -1);
        $middleware->memoryLeakProtection();
        $middleware->set('memory_limit', PHP_INT_MAX);
        $middleware->memoryLeakProtection();
        self::assertSame(PHP_INT_MAX, $middleware->get('memory_limit'));
    }

    public function testGuardReportsExceededLimitWithoutAllocatingHugeData(): void
    {
        $middleware = Middleware::consistent(['memory_limit' => 1]);
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage('Memory limit exceeded');
        $middleware->memoryLeakProtection();
    }

    /** @dataProvider invalidByteLimits */
    public function testInvalidByteLimitsAreRejected($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Middleware::consistent(['memory_limit' => $value]);
    }

    public function invalidByteLimits(): array
    {
        return [[0], [-2], ['1M'], [null], [1.5], [false]];
    }

    /** @dataProvider invalidMegabyteLimits */
    public function testInvalidMegabyteLimitsAreRejected(int $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (Middleware::consistent())->setMemoryLimit($value);
    }

    public function invalidMegabyteLimits(): array
    {
        return [[0], [-1], [PHP_INT_MAX]];
    }

    public function testUnlimitedPhpSettingAndRepeatedMegabyteUpdates(): void
    {
        $previous = ini_get('memory_limit');

        try {
            self::assertNotFalse(ini_set('memory_limit', '-1'));
            $middleware = Middleware::consistent();
            $middleware->setMemoryLimit(64);
            self::assertSame(64 * 1024 * 1024, $middleware->get('memory_limit'));
            $middleware->setMemoryLimit(96);
            self::assertSame(96 * 1024 * 1024, $middleware->get('memory_limit'));
        } finally {
            ini_set('memory_limit', $previous);
        }
    }

    public function testPhpLimitCapsOnlyTheLocalOption(): void
    {
        $previous = ini_get('memory_limit');
        $mb = max(128, (int) ceil(memory_get_usage(true) / (1024 * 1024)) + 64);

        try {
            self::assertNotFalse(ini_set('memory_limit', $mb . 'M'));
            $middleware = Middleware::consistent();
            $middleware->setMemoryLimit($mb + 64);
            self::assertSame($mb * 1024 * 1024, $middleware->get('memory_limit'));
            self::assertSame($mb . 'M', ini_get('memory_limit'));
        } finally {
            ini_set('memory_limit', $previous);
        }
    }
}
