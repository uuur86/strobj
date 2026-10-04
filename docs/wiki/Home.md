# StrObj

StrObj reads, writes and validates nested PHP arrays, objects and JSON documents
through slash-separated paths such as `order/items/0/sku`. It has no runtime
dependencies beyond the JSON extension, so it fits any framework
or plain PHP application.

```php
use StrObj\StringObjects;

$order = StringObjects::instance($json);

$order->get('order/items/0/sku');         // "A-1"
$order->get('order/note', 'n/a');         // "n/a" — the field is missing
$order->set('order/shipping/city', 'Ankara');
$order->isValid();                        // checks the configured rules
```

## Pages

| Topic | Page |
| --- | --- |
| Requirements and Composer setup | [Installation](Installation) |
| Paths, reading, writing and wildcards | [Getting Started](Getting-Started) |
| How `false`, `null`, missing and rejected values are returned | [Values, Defaults and Missing Fields](Values,-Defaults-and-Missing-Fields) |
| Regular-expression rules for stored data | [Validation](Validation) |
| Output casts and acceptance callbacks | [Filters](Filters) |
| Exceptions and how to handle them | [Error Handling](Error-Handling) |
| Laravel controllers, services and tests | [Laravel Integration](Laravel-Integration) |
| Plain PHP, PSR-7 and other frameworks | [Custom PHP Integration](Custom-PHP-Integration) |
| Secure use with untrusted input | [Security Guide](Security-Guide) |
| Upgrading from 2.x | [Migration from 2.1](Migration-from-2.1) |

## Behaviors

`StringObjects::instance()` has two behaviors, selected with the `behavior` option:

- **Consistent** (the default) — stored values are returned exactly as stored,
  defaults apply only to missing or rejected values, values are detached from the
  input, and configuration is checked strictly.
- **Legacy** (`['behavior' => Behavior::LEGACY]`) — keeps the 2.x results that
  existing applications rely on.

See [Migration from 2.1](Migration-from-2.1) for the differences.

> The consistent default ships in version 3.0. Until 3.0.0 is tagged, it is
> available from the `v2.2-dev` branch.
