# Values, Defaults and Missing Fields

PHP code often has to tell a stored `false` or `null` apart from a field that does
not exist. StrObj makes this explicit.

## The default (consistent) contract

By default, `StringObjects::instance()` follows these rules:

1. `get()` returns the **stored value unchanged**: `false`, `null`, `0`, `''` and
   `[]` are never replaced.
2. The default (`get($path, $default)`) is returned **only** when the concrete
   field does not exist, or when a [filter](Filters) callback rejects the value.
3. `has($path)` reports whether the field exists, regardless of its value.

```php
$data = StringObjects::instance(['f' => false, 'n' => null, 'z' => 0, 'e' => '', 'a' => []]);

$data->get('f', 'default');        // false
$data->get('n', 'default');        // null
$data->get('z', 'default');        // 0
$data->get('e', 'default');        // ''
$data->get('a', 'default');        // []
$data->get('missing', 'default');  // 'default'
$data->get('f/below', 'default');  // 'default' — a path below a scalar does not exist
$data->get('missing');             // false — the default of the default parameter
```

| Situation | `get($path, $default)` | `has($path)` |
| --- | --- | --- |
| Stored `false`, `null`, `0`, `''`, `[]` | The stored value | `true` |
| Field missing | `$default` | `false` |
| Value rejected by a filter callback | `$default` | `true` |
| Wildcard path, an element lacks the field | `null` inside the list | `true` if any element has it |

### Telling every case apart

Use a sentinel default when `false` or `null` are meaningful values:

```php
$missing = new stdClass();
$value = $data->get('settings/notifications', $missing);

if ($value === $missing) {
    // Missing, or rejected by a filter: has() tells which.
    $reason = $data->has('settings/notifications') ? 'rejected' : 'missing';
}
```

## The legacy contract

`StringObjects::instance($data, ['behavior' => Behavior::LEGACY])` keeps the 2.x
results for existing applications:

| Situation | Legacy `get($path, $default)` |
| --- | --- |
| Stored `false` | `$default` |
| Stored `null` | `null` |
| Field missing | `null` (the default is **not** used) |
| Value rejected by a filter callback | `false` |
| Wildcard `prefix/*/field` | `array_column()` result: elements without the field are skipped |

`has()` is reliable in both behaviors. If you rely on the legacy behavior, use
`has()` before `get()` whenever `false` or `null` matter, or migrate to the
default behavior ([Migration from 2.1](Migration-from-2.1)).

## Types after filters

[Filters](Filters) can cast a value (`int`, `float`, `bool`, `string`, `array`,
`object`, `json`). Without a filter, `get()` returns the stored PHP type: JSON
numbers stay `int`/`float`, JSON strings stay `string`, JSON objects become
`stdClass` (or arrays, if you pass an array).

## Copies and references

The default behavior copies values when they enter and leave the container:
changing a returned object does not change the stored data, and changing the input
after creation does not affect the instance. Use `set()` to change data. The legacy
behavior keeps live references, as in v2.1.
