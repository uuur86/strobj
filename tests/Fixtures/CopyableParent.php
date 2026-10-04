<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures;

/** Parent-private state must also survive a snapshot, with no public-property flattening. */
class CopyableParent
{
    private object $parentState;
    protected object $protectedState;
    public static int $instances = 0;
    public string $uninitialized;

    public function __construct()
    {
        $this->parentState = (object) ['value' => 1];
        $this->protectedState = (object) ['value' => 2];
    }

    public function states(): array
    {
        return [$this->parentState, $this->protectedState];
    }
}
