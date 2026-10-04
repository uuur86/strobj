# Filters

Filters change what `get()` returns. They do not change stored data, `toJson()` or
`toArray()`.

```php
$product = StringObjects::consistent(['price' => '19.90', 'stock' => '-3'], [
    'filters' => [
        'price' => ['type' => 'float'],
        'stock' => [
            'type' => 'int',
            'callback' => static function (int $value): bool {
                return $value >= 0;
            },
        ],
    ],
]);

$product->get('price');      // 19.9
$product->get('stock', 0);   // 0 — the callback rejected -3, so the default is returned
$product->has('stock');      // true
```

## Options

| Key | Meaning |
| --- | --- |
| `type` | Cast applied first: `string` (the default), `int`, `float`, `bool`, `array`, `object`, `json` |
| `callback` | Optional predicate receiving the cast value. Returning a falsy value rejects it. |
| `args` | Extra arguments passed to the callback after the value |

```php
'filters' => [
    'quantity' => [
        'type' => 'int',
        'callback' => static function (int $value, int $min, int $max): bool {
            return $value >= $min && $value <= $max;
        },
        'args' => [1, 10],
    ],
],
```

- Filter paths may contain wildcards (`items/*/qty`). An exact path wins over a
  wildcard. Among wildcards, the consistent profile picks the pattern with the most
  literal segments; the legacy profile picks the first configured match.
- The default type is `string`. Set `type` explicitly when filtering numbers,
  booleans, arrays or objects.
- In the consistent profile, any `callable` can be used as `callback`, and a
  rejected value returns the default passed to `get()`. In the legacy profile,
  only closures are called and a rejected value returns `false`.

## Casts that cannot be performed

| Case | Consistent | Legacy |
| --- | --- | --- |
| Object or array to `string`, object to `int`/`float` | `InvalidArgumentException` | Value returned unchanged |
| `json` on a non-string | `InvalidArgumentException` | Scalars decoded as in v2.1 |
| Invalid JSON text with `json` | `JsonException` | `null` |
| Unknown type | `InvalidArgumentException` | Value returned unchanged |

Filters are configuration: never let users choose callbacks or types (see the
[Security Guide](Security-Guide)).
