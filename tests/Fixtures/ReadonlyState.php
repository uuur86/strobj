<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures;

/**
 * Readonly state must keep its identity, as a native clone does.
 * Requires PHP 8.1; tests load it only on supported runtimes.
 */
final class ReadonlyState
{
    public readonly object $state;

    public function __construct()
    {
        $this->state = (object) ['value' => 1];
    }
}
