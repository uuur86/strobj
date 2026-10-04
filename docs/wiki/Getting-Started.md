# Getting Started

## Creating an instance

Both factories accept an array, an object (including `Traversable`) or a JSON
string whose root is an object or array.

```php
use StrObj\StringObjects;

$data = StringObjects::consistent($json);          // recommended
$data = StringObjects::consistent($array, $options);
$data = StringObjects::instance($json);            // legacy v2.1 behavior
```

Invalid input throws `InvalidArgumentException`; see [Error Handling](Error-Handling).

## Paths

A path is a list of keys separated by `/`:

| Path | Meaning |
| --- | --- |
| `order/id` | The `id` field of `order` |
| `order/items/0/sku` | `sku` of the first item; numeric keys address list entries |
| `order/items/*/sku` | `sku` of every item (wildcard) |
| `''` or `*` | The whole document |

Leading, trailing and repeated slashes are ignored (`/order//id/` equals
`order/id`). A path may contain at most 512 segments.

## Reading

```php
$order = StringObjects::consistent(
    '{"order":{"id":42,"paid":false,"coupon":null,"items":[{"sku":"A-1","qty":2},{"sku":"B-2"}]}}'
);

$order->get('order/id');               // 42
$order->get('order/paid', true);       // false — the stored value is returned
$order->get('order/coupon', 'NONE');   // null  — the stored value is returned
$order->get('order/note', 'n/a');      // "n/a" — the field is missing
$order->has('order/coupon');           // true
$order->has('order/note');             // false
```

The rules for `false`, `null` and defaults are described in
[Values, Defaults and Missing Fields](Values,-Defaults-and-Missing-Fields).

### Wildcards

```php
$order->get('order/items/*/qty');      // [2, null] — one entry per item
```

In the consistent profile, a wildcard read returns one entry per element and
lists missing fields as `null`. Nested wildcards (`groups/*/members/*/name`)
return nested lists. `has('order/items/*/qty')` is `true` when at least one
element has the field.

## Writing

```php
$order->set('order/items/1/qty', 1);
$order->set('order/shipping/city', 'Ankara');   // missing branches are created

echo $order->toJson();
// {"order":{"id":42,"paid":false,"coupon":null,
//   "items":[{"sku":"A-1","qty":2},{"sku":"B-2","qty":1}],"shipping":{"city":"Ankara"}}}
```

Writes require a concrete path; empty and wildcard paths throw
`InvalidArgumentException`. Unrelated fields are preserved, and a scalar found on
the way is replaced by a new branch.

## Exporting

| Method | Result |
| --- | --- |
| `toJson()` | A JSON string. Object roots stay objects (`{}`), lists stay lists. |
| `toArray()` | A PHP array. The consistent profile converts every nested object to an array. |

## Options

```php
$data = StringObjects::consistent($input, [
    'validation' => ['patterns' => [/* name => regex */], 'rules' => [/* ... */]],
    'filters'    => [/* path => ['type' => ..., 'callback' => ...] */],
    'middleware' => ['memory_limit' => 256 * 1024 * 1024], // optional, bytes
]);
```

- [Validation](Validation) checks stored data.
- [Filters](Filters) cast and accept values returned by `get()`.
- `middleware.memory_limit` is an optional guard. It compares the **whole PHP
  process's** memory usage (`memory_get_usage()`) with the limit and throws
  `OverflowException` when the limit is exceeded. Use a value above your
  application's normal peak, or omit it.

Options are application configuration. Never build them from user input; see
the [Security Guide](Security-Guide).
