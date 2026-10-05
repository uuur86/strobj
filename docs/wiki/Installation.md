# Installation

## Requirements

- PHP 7.4 or newer (tested on 7.4 through 8.5)
- The `json` extension

## Composer

```bash
composer require uuur86/strobj:^3.0
```

Applications that still need the 2.x line can require `uuur86/strobj:^2.1`; see
[Migration from 2.1](Migration-from-2.1) before upgrading.

## Autoloading

Composer's autoloader registers the `StrObj\` namespace:

```php
require __DIR__ . '/vendor/autoload.php';

use StrObj\StringObjects;
```

## Verifying the installation

```php
$data = StringObjects::instance(['status' => 'ok']);
echo $data->get('status'); // ok
```

Next: [Getting Started](Getting-Started).
