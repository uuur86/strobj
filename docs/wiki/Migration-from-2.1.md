# Migration from 2.1

Version 2.2 keeps existing applications working: `StringObjects::instance()` uses
the **legacy** profile, which preserves the v2.1 results that applications rely
on. New code should use `StringObjects::consistent()`.

## What changes when you switch to `consistent()`

| Behavior | `instance()` (legacy) | `consistent()` |
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
[docs/compatibility.md](https://github.com/uuur86/strobj/blob/v2.2-dev/docs/compatibility.md).

## Fixes that apply to both profiles

- Deep writes keep the whole document.
- `has()` reports existing `false` and `null` fields correctly.
- Reads always reflect the current data; validation refreshes after writes.
- Numeric root keys (`0/name`) work on every PHP version.
- Library errors are exceptions (`InvalidArgumentException`, `JsonException`)
  instead of PHP `Error`s or warnings.

## A safe migration path

1. Upgrade to 2.2 without changing code. `instance()` keeps the legacy behavior.
2. Add `has()` checks wherever your code compares `get()` results with `false` or
   `null`.
3. Switch one module at a time to `consistent()` and run its tests.
4. Search for code that relies on legacy-only results:
   - `get(..., $default)` on fields that can store `false`
   - `count()` or index access on wildcard results
   - `->` access on values returned by `toArray()`
   - code that mutates returned objects and expects the stored data to change
5. Replace those with explicit `has()` checks, `set()` calls and the consistent
   wildcard shape.
