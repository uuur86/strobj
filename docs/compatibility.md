# Compatibility and behavior profiles

Since 3.0, `StringObjects::instance()` uses the **consistent** behavior by default.
Applications written for 2.x select the **legacy** behavior explicitly to keep
their previous results:

```php
use StrObj\Behavior;
use StrObj\StringObjects;

$consistent = StringObjects::instance($data, $options);
$legacy = StringObjects::instance($data, ['behavior' => Behavior::LEGACY] + $options);
```

Migrate an application deliberately and test its own expectations before removing
the legacy option.

## Observable differences

| Contract | Legacy (`Behavior::LEGACY`) | Consistent (default) |
| --- | --- | --- |
| Missing concrete field with a default | Returns `null` | Returns the supplied default |
| Existing `false` with a default | Returns the supplied default | Returns `false` |
| Existing `null` | Returns `null` | Returns `null` |
| Value rejected by a filter callback | Returns `false` | Returns the supplied default |
| Input/returned object references | Preserves existing live references | Copies writable object state |
| Empty object root in facade JSON | `[]`, as before | `{}` |
| Wildcard read `prefix/*/field` | `array_column()` contract from v2.1: rows without the field are skipped and later segments are ignored | One entry per row (`null` for a missing field); every segment and nested wildcard is resolved |
| `toArray()` | Root fields with nested values unchanged, as in v2.1 | Every nested container converted to arrays through its JSON representation |
| Unknown filter cast | Leaves the value unchanged | Throws `InvalidArgumentException` |
| Invalid JSON cast | Returns `null` | Throws `JsonException` |
| Cast PHP cannot perform (object or array to string, object to number, non-string JSON) | Returns the value unchanged; scalar JSON casts decode as in v2.1 | Throws `InvalidArgumentException` |
| Predicate callbacks | Closures, as before | All callable forms |
| Tree filtering | Historical array leaf-name matching; objects cast directly | Complete path matching on a copied tree |
| Callback argument arrays | Original keys, including PHP 8 named arguments | Positional values |
| Filter precedence | Exact key first, then configuration order | Exact path first, then greatest literal specificity |
| Validation `required` flag | Accepts scalar boolean coercion | Requires a boolean |
| Byte limit configuration | Accepts finite numeric values and numeric strings | Requires an integer |
| Factory called on a subclass | Returns `StringObjects`, as before | Preserves the called subclass |

Stored-data bug fixes apply to both profiles: complete deep writes, reads that
always reflect current data, reliable field existence, and validation refreshed
after library mutations. Code relying on a stale read or a formerly skipped validation
failure can observe a changed result. Invalid configurations, cycles in snapshots,
empty/wildcard write paths, and out-of-range limits are rejected explicitly.
These changes do not provide a guarantee for every application or unsupported input.

## Public APIs and subclass compatibility

Existing public and protected method names, parameter names, required arguments
and return declarations are retained. Parameter names matter for PHP 8 named calls.
Type descriptions stay in docblocks where adding a native declaration would
prevent an existing subclass from loading. New operations have distinct names:

- `DataCache::clear($path)` keeps its original signature; `clearAll()` clears everything.
- `DataObject::query($path)` keeps its original signature; `queryWithTransform()`
  adds concrete-path transformation.
- `DataObject::jsonSerialize(): array` retains its original contract;
  `toJsonValue()` additionally preserves object-root shape.
- `DataObject::snapshot($data)` opts direct container users into copying.
- `DataFilters::consistent()`, `Validation::consistent()` and
  `Middleware::consistent()` opt direct component users into strict configuration.
- `castType()` retains permissive casts; `castTypeStrict()` rejects unknown types
  and invalid JSON.

### Direct offset writes

`offsetSet($index, $val): void` remains public, including its original `val`
parameter name and virtual dispatch from `set()`, `append()` and array syntax.
Its direct-call API is documented with `@deprecated` in favor of `setOffset()`:

```php
$data->offsetSet(index: 'age', val: 21); // Existing PHP 8 calls remain valid.
$data->setOffset('age', 21);            // Preferred direct call.
$data['age'] = 21;                     // ArrayAccess remains supported.
```

The compatibility wrapper delegates to the same write and revision logic.
It emits no runtime deprecation notice: SPL itself requires `offsetSet()`, and
application error handlers may turn a notice into an exception. There is no
planned removal of ArrayAccess support.

## Object copying and serialization

Nested custom classes, their methods, declared private/protected state and
`JsonSerializable` output are retained. Native objects such as `DateTimeImmutable`
retain their metadata. JSON/array export uses PHP's serialization contract rather
than flattening every object into public properties.

Snapshots use native `clone`, then recursively copy writable declared/dynamic
properties and array entries. An object's `__clone()` hook is respected.
Writable collections implementing both `Traversable` and `ArrayAccess` copy their
entries through those interfaces. Nested collection paths use those protocols;
non-Traversable ArrayAccess values use their own `offsetExists()` contract.
SPL array collections reinitialize native backing storage to avoid clone aliases,
while retaining their subclass, flags and configured iterator class.
Asymmetric setters use their declaring class scope; computed virtual properties
are derived from copied state. Readonly properties and opaque internal state
follow the native clone contract.
Uncloneable objects and resources retain their identity. Accordingly, snapshots
are not a universal isolation mechanism for handles or mutable objects held in
readonly/internal state; use immutable values or an appropriate `__clone()` hook
for those cases. Aliasing between repeated object references is not guaranteed.
Cycles in copied writable state and depth above 512 are rejected before committing
a write. Object roots expose public container fields; custom serializers are
preserved for nested values.

### Object roots and PHP 8.5

Snapshots, including every consistent `StringObjects` instance, store the public
entries of an object root in an array and remember that the root was an object
for JSON output. Non-public properties of a class-instance root are not exposed,
and appended values never replace existing numeric entries. PHP 8.5 deprecates
objects as `ArrayObject` and `ArrayIterator` storage; snapshots do not use it.

The legacy profile and `new DataObject($object)` keep the caller's object as SPL
storage, so writes still reach that object as in v2.1. On PHP 8.5 this emits an
`E_DEPRECATED` notice for each object root. Use the consistent profile, or pass
arrays to `DataObject`, to avoid it. A future PHP version that removes object
storage will require a change to this legacy contract.

In the consistent profile, `toArray()` follows JSON export rules. Sparse numeric
keys, invalid UTF-8, resources and custom serializers retain PHP's normal JSON
constraints. The legacy profile returns root fields with nested values unchanged.

## Inherited SPL operations

Sorting and deserialization retain SPL's own parameter and return contracts,
which vary across PHP versions. Reads always resolve the current storage, so they
need no change detection. Revision and validation checks compare the root storage
with the previous check, rather than maintaining six version-dependent sort
wrappers. Snapshot child iterators keep the copy policy.

Reads cost time proportional to the path depth. Root wildcard reads, `toArray()`
and revision checks are linear in the root size. Snapshot copying also scales with
the selected structure. Measure large documents in the consuming application's
workload before adopting snapshots. Legacy live-reference mutations below the root
are not tracked as revisions; use library setters when validation freshness matters.

## Verification

`CompatibilityTest` compares all recorded v2.1 public/protected signatures and
loads consumer subclasses that override every recorded method with its original
signature (`tests/Fixtures/Consumers`). The overrides delegate to the library,
so a smoke test also runs them. It also checks native SPL contracts, named
arguments, old defaults, coercions and object identity. `ObjectRootStorageTest`
fails when the consistent profile raises a deprecation, whatever the
`error_reporting` setting is.
`ValueCopierTest` covers custom/inherited state, serializers, clone hooks, native
objects, readonly state, handles and rejected cycles. Existing deep-write and
workflow regressions run alongside these tests.

Full executable-line coverage is enforced for every source file. Line coverage
does not prove all input combinations or consuming-application behavior. The CI
matrix includes PHP 7.4 and 8.0–8.5; locally installed versions and executed
checks are recorded in the review report.
