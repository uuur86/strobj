# Installation

## Requirements

- PHP 7.4 or newer (tested on 7.4 through 8.5)
- The `json` extension

## Composer

```bash
composer require uuur86/strobj
```

Version 3.0 is in development. To use it before the 3.0.0 release, require the
development branch:

```bash
composer require uuur86/strobj:dev-v2.2-dev
```

Pin a tagged release in production as soon as one is available.

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
