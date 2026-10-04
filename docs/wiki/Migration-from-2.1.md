# Migration from 2.1

Version 3.0 changes the default behavior of `StringObjects::instance()`. Stored
values are returned unchanged, defaults apply only to missing or rejected values,
and wildcard reads keep one entry per element. Composer constraints such as `^2.1`
do not install 3.0 automatically, so existing applications keep working until you
upgrade them deliberately.

## Upgrading without behavior changes

Add the legacy option wherever you create an instance:

```php
use StrObj\Behavior;
use StrObj\StringObjects;

$data = StringObjects::instance($input, ['behavior' => Behavior::LEGACY]);
$data = StringObjects::instance($input, ['behavior' => Behavior::LEGACY] + $options);
```

The legacy behavior keeps the 2.1 results; only the bug fixes listed below apply.

## What changes with the default behavior

| Behavior | Legacy (`Behavior::LEGACY`) | Default (consistent) |
| --- | --- | --- |
| Stored `false` with a default | Returns the default | Returns `false` |
| Missing field with a default | Returns `null` | Returns the default |
| Value rejected by a filter | Returns `false` | Returns the default |
| Wildcard `prefix/*/field` | `array_column()`: missing elements skipped, later segments ignored | One entry per element (`null` when missing), every segment resolved |
| `toArray()` | Root fields; nested objects kept | Every nested object converted to arrays |
| Input and returned objects | Live references | Independent copies |
| Empty object root in `toJson()` | `[]` | `{}` |
| Filter callbacks | Closures only | Any callable |
| Casts that PHP cannot perform | Value returned unchanged | `InvalidArgumentException` |
| Filter precedence among wildcards | Configuration order | Most literal segments |
| `required` flag, byte limits | Coerced from scalars and numeric strings | Must be `bool` / `int` |
| Factory called on a subclass | Returns `StringObjects` | Returns the subclass |

The complete table is in
[docs/compatibility.md](https://github.com/uuur86/strobj/blob/3.0.x-dev/docs/compatibility.md).

## Fixes that apply to both behaviors

- Deep writes keep the whole document.
- `has()` reports existing `false` and `null` fields correctly.
- Reads always reflect the current data; validation refreshes after writes.
- Numeric root keys (`0/name`) work on every PHP version.
- Library errors are exceptions (`InvalidArgumentException`, `JsonException`)
  instead of PHP `Error`s or warnings.

## A safe migration path

1. Upgrade to 3.0 and add `['behavior' => Behavior::LEGACY]` to every instance.
   Run your tests; only the listed bug fixes should be visible.
2. Add `has()` checks wherever your code compares `get()` results with `false` or
   `null`.
3. Remove the legacy option one module at a time and run its tests.
4. Search for code that relies on legacy-only results:
   - `get(..., $default)` on fields that can store `false`
   - `count()` or index access on wildcard results (legacy wildcards skip elements
     without the field, which can shift values between rows)
   - `->` access on values returned by `toArray()`
   - code that mutates returned objects and expects the stored data to change
5. Replace those with explicit `has()` checks, `set()` calls and the aligned
   wildcard shape.
