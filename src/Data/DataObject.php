<?php

/**
 * This file is part of the StrObj package.
 *
 * (c) Uğur Biçer <contact@fyndsoft.com>
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
 * Reads always resolve the current storage. Revision checks observe the storage,
 * so validation also refreshes after these operations, including partial
 * mutations before a comparator throws.
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
     * Latest query path
     *
     * @var string
     */
    private string $currentPath = '';
    /**
     * Auxiliary cache populated only by cache(); reads never consult it.
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

    /**
     * Storage observed by the latest revision check, used to detect inherited
     * SPL mutations. Null after a library write, which already bumps the revision.
     *
     * @var array|null
     */
    private ?array $observedData = null;

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
        parent::__construct($this->objectRoot ? PathResolver::objectFromEntries($data) : $data, $this->getFlags());
        $this->observedData = parent::getArrayCopy();
    }

    /** @return mixed A value copied according to the container's policy. */
    private function copyStoredValue($value)
    {
        return $this->detached ? PathResolver::copyValue($value) : $value;
    }

    /**
     * Initializes the current path and returns an independent path iterator.
     * Parsed paths are not retained, so distinct queries do not accumulate memory.
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

        return new DataPath($canonical);
    }

    /**
     * Returns the revision of the current data.
     * Inherited SPL mutations made since the previous check also advance it.
     *
     * @return int
     */
    public function getRevision(): int
    {
        $data = parent::getArrayCopy();

        if ($this->observedData !== null && $data !== $this->observedData) {
            $this->revision++;
            $this->cache->clearAll();
        }

        $this->observedData = $data;

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
     * Save data to the auxiliary cache
     * Retained for API compatibility; get() always resolves the current data.
     *
     * @param string $path
     * @param mixed  $value
     */
    public function cache(string $path, $value): void
    {
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
        return $this->query($path);
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

        if ($segments[0] === '*') {
            return PathResolver::read($this->readStorage(), $segments, $transform, '', $this->detached);
        }

        $key = array_shift($segments);
        $root = $this->lookupRoot($key);

        if (!$root['exists']) {
            return null;
        }

        return PathResolver::read($root['value'], $segments, $transform, $key, $this->detached);
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
        $segments = $this->pathInit($path)->getArray();

        if ($segments === [] || $segments[0] === '*') {
            return PathResolver::select($this->readStorage(), $segments, '', $this->detached);
        }

        $key = array_shift($segments);
        $root = $this->lookupRoot($key);

        if (!$root['exists']) {
            return [implode('/', array_merge([$key], $segments)) => ['exists' => false, 'value' => null]];
        }

        return PathResolver::select($root['value'], $segments, $key, $this->detached);
    }

    /**
     * Reads one root field through the SPL storage in constant time.
     * Numeric property names of object roots are also found this way.
     *
     * @param string $key The root field.
     *
     * @return array{exists: bool, value: mixed}
     */
    private function lookupRoot(string $key): array
    {
        if (parent::offsetExists($key)) {
            return ['exists' => true, 'value' => parent::offsetGet($key)];
        }

        // SPL before PHP 8.1 misses numeric property names of objects supplied by the caller.
        $fields = PHP_VERSION_ID < 80100 && $this->objectRoot ? (array) (object) parent::getArrayCopy() : [];
        $exists = array_key_exists($key, $fields);

        return ['exists' => $exists, 'value' => $exists ? $fields[$key] : null];
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
        $key = array_shift($segments);
        $root = $this->lookupRoot($key);
        $updated = PathResolver::write($root['exists'] ? $root['value'] : [], $segments, $value, $this->detached);
        $this->offsetSet($key, $updated);
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
        $this->observedData = null;
    }

    /**
     * Returns the complete iterator storage for whole-tree and root wildcard reads.
     *
     * @return array The current iterator storage.
     */
    private function readStorage(): array
    {
        return parent::getArrayCopy();
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
     * Converts the data to an array
     * Legacy containers return root fields with nested values unchanged, as in v2.1.
     * Snapshot containers recursively convert every nested container to arrays.
     *
     * @return array
     */
    public function toArray(): array
    {
        if ($this->detached) {
            return PathResolver::copyValue($this->readStorage(), true);
        }

        $result = [];

        // Assignment normalizes numeric property names of object roots to integer keys.
        foreach ($this->readStorage() as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
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
