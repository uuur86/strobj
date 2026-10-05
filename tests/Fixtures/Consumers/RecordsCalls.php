<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures\Consumers;

/** Records which consumer overrides ran before they delegate to the library. */
trait RecordsCalls
{
    /** @var string[] Overridden methods called on the using class, in call order. */
    public static array $calls = [];

    /** Appends one overridden method name to the call log. */
    private static function record(string $method): void
    {
        self::$calls[] = $method;
    }
}
