<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures;

/**
 * Asymmetric visibility and a computed property must follow their declaring scope.
 * Requires PHP 8.4; tests load it only on supported runtimes.
 */
final class HookedState
{
    // Public reads with private writes; PHP 8.4 implies the public read visibility.
    private(set) object $state;

    public int $age {
        get => $this->state->age;
    }

    public function __construct()
    {
        $this->state = (object) ['age' => 12];
    }
}
