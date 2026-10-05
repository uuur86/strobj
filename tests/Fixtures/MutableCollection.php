<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/** A consumer collection with declared backing state and only public container protocols. */
final class MutableCollection implements ArrayAccess, IteratorAggregate, JsonSerializable
{
    private array $entries;

    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entries);
    }

    public function offsetExists($key): bool
    {
        return array_key_exists($key, $this->entries);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        return $this->entries[$key];
    }

    public function offsetSet($key, $value): void
    {
        $this->entries[$key] = $value;
    }

    public function offsetUnset($key): void
    {
        unset($this->entries[$key]);
    }

    public function jsonSerialize(): array
    {
        return $this->entries;
    }
}
