<?php

/**
 * This file is part of the StrObj package.
 *
 * (c) Uğur Biçer <contact@codeplus.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package  StrObj
 * @version  GIT: <git_id>
 * @link     https://github.com/uuur86/strobj
 */

declare(strict_types=1);

namespace StrObj\Data;

use InvalidArgumentException;
use RecursiveArrayIterator;
use StrObj\Helpers\PathResolver;
use StrObj\Interfaces\DataStructures\DataInterface;
use Traversable;

/**
 * A path-aware data container that retains the inherited SPL operation contracts.
 *
 * Inherited asort/ksort sort values/keys and forward PHP's native comparison flags.
 * Inherited natsort/natcasesort sort values in natural/case-insensitive natural order.
 * Inherited uasort/uksort accept native comparators and preserve keys.
 * Shared storage observation invalidates cached reads after these operations,
 * including partial mutations before a comparator throws.
 *
 * @see RecursiveArrayIterator::asort()
 * @see RecursiveArrayIterator::ksort()
 * @see RecursiveArrayIterator::natsort()
 * @see RecursiveArrayIterator::natcasesort()
 * @see RecursiveArrayIterator::uasort()
 * @see RecursiveArrayIterator::uksort()
 */
class DataObject extends RecursiveArrayIterator implements DataInterface
{
    /**
     * @var DataPath[]
     */
    private array $paths = [];
    /**
     * Latest query path
     *
     * @var string
     */
    private string $currentPath = '';
    /**
     * Cache object
     *
     * @var DataCache
     */
    private DataCache $cache;
    /**
     * Preserves an object root when encoding JSON.
     *
     * @var bool
     */
    private bool $objectRoot;

    /** @var bool Whether reads and writes detach mutable object state. */
    private bool $detached = false;

    /** @var array Last observed SPL storage, used to detect inherited mutations. */
    private array $observedData = [];

    /**
     * Mutation counter used to invalidate validation results.
     *
     * @var int
     */
    private int $revision = 0;

    /**
     * Constructor
     *
     * @param array|object $data The object or array to use.
     */
    public function __construct($data)
    {
        if (!is_array($data) && !is_object($data)) {
            throw new InvalidArgumentException('Data must be an array or object.');
        }

        $this->cache = new DataCache();
        $this->objectRoot = is_object($data) && !$data instanceof Traversable;
        parent::__construct($data instanceof Traversable ? iterator_to_array($data) : $data);
        $this->observedData = parent::getArrayCopy();
    }

    /**
     * Creates a data container with detached reads and writes.
     *
     * @param array|object $data The input data.
     * @return self
     */
    public static function snapshot($data): self
    {
        $object = new self($data);
        $object->detached = true;
        $object->resetStorage();

        return $object;
    }

    /**
     * Reinitializes SPL storage and cache when cloning the data object.
     * Nested values follow the existing legacy or snapshot copy policy.
     * The clone starts at the first iterator entry with the same data revision.
     *
     * @return void
     */
    public function __clone()
    {
        $this->resetStorage();
    }

    /** Reinitializes iterator storage and cache according to the copy policy. */
    private function resetStorage(): void
    {
        $data = $this->copyStoredValue(parent::getArrayCopy());
        $this->cache = new DataCache();
        parent::__construct($this->objectRoot ? (object) $data : $data, $this->getFlags());
        $this->observedData = parent::getArrayCopy();
    }

    /** @return mixed A value copied according to the container's policy. */
    private function copyStoredValue($value)
    {
        return $this->detached ? PathResolver::copyValue($value) : $value;
    }

    /**
     * Initializes the current path and returns an independent path iterator.
     *
     * @param string $path The slash-separated query path.
     *
     * @return DataPath
     */
    public function pathInit(string $path): DataPath
    {
        $parsed = new DataPath($path);
        $canonical = $parsed->normalizePath($path);
        $this->currentPath = $canonical;

        if (!isset($this->paths[$canonical])) {
            $this->paths[$canonical] = new DataPath($canonical);
        }

        return new DataPath($this->paths[$canonical]->getRaw());
    }

    /**
     * Returns the revision of the current data.
     *
     * @return int
     */
    public function getRevision(): int
    {
        $this->readStorage();

        return $this->revision;
    }

    /**
     * Get latest query path
     *
     * @return string
     */
    public function getCurrentPath(): string
    {
        return $this->currentPath;
    }

    /**
     * Save data to cache
     *
     * @param string $path
     * @param mixed  $value
     */
    public function cache(string $path, $value): void
    {
        $this->readStorage();
        $canonical = $this->pathInit($path)->normalizePath($path);
        $this->cache->save($canonical, $this->copyStoredValue($value));
    }

    /**
     * Get value if exists, otherwise return null
     *
     * @param string $path
     *
     * @return mixed
     */
    public function get(string $path)
    {
        $this->readStorage();
        $canonical = $this->pathInit($path)->normalizePath($path);

        if (strpos($canonical, '*') !== false) {
            return $this->query($canonical);
        }

        if ($this->cache->isCached($canonical)) {
            return $this->copyStoredValue($this->cache->get($canonical));
        }

        $value = $this->query($canonical);
        $this->cache($canonical, $value);

        return $value;
    }

    /**
     * Queries the data with the given path.
     *
     * @param string|null $path The path of the requested value. Default is null.
     * @return mixed Returns the queried data or null if not found.
     */
    public function query(?string $path = null)
    {
        return $this->queryWithTransform($path);
    }

    /**
     * Queries data and optionally transforms each selected concrete value.
     *
     * @param string|null $path The requested path.
     * @param callable|null $transform Receives the concrete path and value.
     * @return mixed
     */
    public function queryWithTransform(?string $path = null, ?callable $transform = null)
    {
        $parsed = $this->pathInit($path ?? '');
        $segments = $parsed->getArray();

        if ($segments === [] || $parsed->getRaw() === '*') {
            return PathResolver::read($this->readStorage(), [], $transform, '', $this->detached);
        }

        return PathResolver::read($this->readStorage(), $segments, $transform, '', $this->detached);
    }

    /**
     * Finds concrete matches, retaining original keys and missing fields.
     *
     * @param string $path The path or wildcard pattern.
     *
     * @return array<string, array{exists: bool, value: mixed}>
     */
    public function findMatches(string $path): array
    {
        return PathResolver::select($this->readStorage(), $this->pathInit($path)->getArray(), '', $this->detached);
    }

    /**
     * Set a value to the given concrete path, preserving unrelated fields.
     * New branches are created and scalar intermediates become arrays.
     * The changed branch is built before committing the write.
     *
     * @throws \InvalidArgumentException For an empty or wildcard write path.
     * @see https://github.com/php/php-src/issues/10519
     *
     * @param string $path
     * @param mixed  $value
     */
    public function set(string $path, $value): void
    {
        $segments = $this->pathInit($path)->getArray();

        if ($segments === [] || in_array('*', $segments, true)) {
            throw new InvalidArgumentException('Writes require a non-empty concrete path.');
        }

        $value = $this->copyStoredValue($value);
        $updated = PathResolver::write($this->readStorage(), $segments, $value, $this->detached);
        $this->offsetSet($segments[0], $updated[$segments[0]]);
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $index The original ArrayAccess index parameter.
     * @param mixed $val The original value parameter (including named calls).
     * @deprecated For direct calls, use setOffset(). ArrayAccess syntax remains supported.
     */
    public function offsetSet($index, $val): void
    {
        $this->setOffset($index, $val);
    }

    /**
     * Writes an iterator field and invalidates cached reads.
     *
     * @param mixed $index The field index; null appends.
     * @param mixed $value The value to store.
     * @return void
     */
    public function setOffset($index, $value): void
    {
        parent::offsetSet($index, $this->copyStoredValue($value));
        $this->invalidate();
    }

    /**
     * Removes an iterator field and invalidates cached reads.
     *
     * @param string|int $key The field to remove.
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($key)
    {
        parent::offsetUnset($key);
        $this->invalidate();
    }

    /**
     * Appends a value and invalidates cached reads.
     *
     * @param mixed $value The value to append.
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function append($value)
    {
        $this->offsetSet(null, $value);
    }

    /**
     * Discards cached reads and marks validation results as outdated.
     *
     * @return void
     */
    private function invalidate(): void
    {
        $this->cache->clearAll();
        $this->revision++;
        $this->observedData = parent::getArrayCopy();
    }

    /**
     * Detects mutations made by inherited SPL methods without overriding their signatures.
     * PHP's array comparison also detects ordering changes and uses copy-on-write storage.
     *
     * @return array The current iterator storage.
     */
    private function readStorage(): array
    {
        $data = parent::getArrayCopy();

        if ($data !== $this->observedData) {
            $this->invalidate();
        }

        return $data;
    }

    /**
     * Returns an iterator value according to the container's copy policy.
     *
     * @param string|int $key The requested field.
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        return $this->copyStoredValue(parent::offsetGet($key));
    }

    /**
     * Returns the current iterator value according to the container's copy policy.
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->copyStoredValue(parent::current());
    }

    /**
     * Preserves the copy policy when recursive iteration creates a child iterator.
     *
     * @return RecursiveArrayIterator|null
     */
    #[\ReturnTypeWillChange]
    public function getChildren()
    {
        $child = parent::getChildren();

        if ($this->detached && $child instanceof self) {
            $child->detached = true;
            $child->resetStorage();
        }

        return $child;
    }

    /**
     * Returns an array copy without changing nested classes or list/object shapes.
     * Nested values follow the legacy or snapshot copy policy.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function getArrayCopy()
    {
        return $this->copyStoredValue($this->readStorage());
    }

    /**
     * {@inheritdoc}
     */
    public function getCols(string $colname): array
    {
        return $this->query('*/' . $colname);
    }

    /**
     * {@inheritdoc}
     *
     * @return array The original serialization contract, including object roots.
     */
    public function jsonSerialize(): array
    {
        return $this->getArrayCopy();
    }

    /** @return array|object A JSON value preserving the original root container shape. */
    public function toJsonValue()
    {
        $data = $this->getArrayCopy();

        return $this->objectRoot ? (object) $data : $data;
    }

    /**
     * Recursively converts the data to arrays
     *
     * @return array
     */
    public function toArray(): array
    {
        return PathResolver::copyValue($this->readStorage(), true);
    }

    /**
     * Find path
     *
     * @param string $key
     *
     * @return bool
     */
    public function findKey(string $key): bool
    {
        $this->rewind();

        while ($this->valid()) {
            if ($this->key() === $key) {
                return true;
            }

            $this->next();
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function key(): string
    {
        return (string) parent::key();
    }

    /**
     * Checks field existence independently of its value or query history.
     * Wildcards require at least one existing concrete match.
     *
     * @param string $path
     *
     * @return bool
     */
    public function has(string $path): bool
    {
        foreach ($this->findMatches($path) as $match) {
            if ($match['exists']) {
                return true;
            }
        }

        return false;
    }
}
