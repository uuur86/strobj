<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures;

/**
 * Asymmetric visibility and a computed property must follow their declaring scope.
 * Requires PHP 8.4; tests load it only on supported runtimes.
 */
final class HookedState
{
    // Public reads with private writes. Sonar's PHP parser misreads the PER-CS modifier order.
    public private(set) object $state; // NOSONAR

    public int $age {
        get => $this->state->age;
    }

    public function __construct()
    {
        $this->state = (object) ['age' => 12];
    }
}
