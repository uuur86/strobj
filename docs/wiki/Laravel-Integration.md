# Laravel Integration

StrObj needs no service provider, facade or configuration to work in Laravel.
Each `StringObjects` instance wraps one document and holds no static state, so it
is safe in queues, Horizon workers and Octane.

The examples target Laravel 10 and later; they use only stable framework APIs.

## 1. Install

```bash
composer require uuur86/strobj
```

## 2. Read a JSON request in a controller

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use StrObj\StringObjects;

final class WebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = StringObjects::consistent($request->getContent(), [
                'validation' => ['rules' => [
                    ['path' => 'event', 'pattern' => '/^(order\.paid|order\.refunded)$/', 'required' => true],
                    ['path' => 'data/order/id', 'pattern' => '/^\d+$/', 'required' => true],
                    ['path' => 'data/order/items/*/sku', 'pattern' => '/^[A-Z0-9-]{3,30}$/', 'required' => true],
                ]],
                'filters' => [
                    'data/order/id' => ['type' => 'int'],
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => 'The request body must be a JSON object.'], 400);
        }

        if (!$payload->isValid()) {
            return response()->json(['message' => 'The payload is invalid.'], 422);
        }

        $orderId = $payload->get('data/order/id');               // int
        $coupon = $payload->get('data/order/coupon', null);      // null when missing
        $skus = $payload->get('data/order/items/*/sku');         // list of SKUs

        // ...

        return response()->json(['received' => true]);
    }
}
```

- `$request->getContent()` passes the raw body, so StrObj decodes the JSON and
  reports invalid documents with exception code 22.
- When the request was already parsed, pass the array instead:
  `StringObjects::consistent($request->json()->all())`.
- Laravel limits request sizes at the web server and PHP level (`post_max_size`).
  Keep those limits in place; StrObj does not limit document size.

## 3. Keep rules in a dedicated class

Move options out of controllers so they can be reused and unit tested:

```php
<?php

namespace App\Payloads;

use InvalidArgumentException;
use StrObj\StringObjects;

final class OrderPayload
{
    private const OPTIONS = [
        'validation' => [
            'patterns' => ['sku' => '/^[A-Z0-9-]{3,30}$/'],
            'rules' => [
                ['path' => 'order/id', 'pattern' => '/^\d+$/', 'required' => true],
                ['path' => 'order/items/*/sku', 'pattern' => 'sku', 'required' => true],
                ['path' => 'order/items/*/qty', 'pattern' => '/^[1-9]\d{0,3}$/', 'required' => true],
            ],
        ],
        'filters' => [
            'order/id' => ['type' => 'int'],
            'order/items/*/qty' => ['type' => 'int'],
        ],
    ];

    private StringObjects $data;

    private function __construct(StringObjects $data)
    {
        $this->data = $data;
    }

    /** @throws InvalidPayload */
    public static function fromJson(string $json): self
    {
        try {
            $data = StringObjects::consistent($json, self::OPTIONS);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidPayload('The order document is not valid JSON.', 0, $exception);
        }

        if (!$data->isValid()) {
            throw new InvalidPayload('The order document does not match the expected format.');
        }

        return new self($data);
    }

    public function id(): int
    {
        return $this->data->get('order/id');
    }

    /** @return list<int> */
    public function quantities(): array
    {
        return $this->data->get('order/items/*/qty');
    }
}
```

```php
<?php

namespace App\Payloads;

use RuntimeException;

final class InvalidPayload extends RuntimeException
{
}
```

Render the domain exception once, in `bootstrap/app.php` (Laravel 11 and later):

```php
<?php

use App\Payloads\InvalidPayload;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (InvalidPayload $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        });
    })
    ->create();
```

Only the `withExceptions()` callback is new; keep the rest of your existing file.

On Laravel 10, register the same callback in `App\Exceptions\Handler::register()`
with `$this->renderable(...)`.

## 4. Optional: shared configuration

If several classes share patterns, keep them in `config/strobj.php`:

```php
<?php

return [
    'patterns' => [
        'sku' => '/^[A-Z0-9-]{3,30}$/',
        'iso_date' => '/^\d{4}-\d{2}-\d{2}$/',
    ],
];
```

```php
$options = ['validation' => ['patterns' => config('strobj.patterns'), 'rules' => $rules]];
```

Configuration files are trusted code. Never merge request data into options.

## 5. JSON columns in Eloquent models

For a JSON column cast to `array`, wrap the attribute and write the result back:

```php
$settings = StringObjects::consistent($user->settings ?? []);
$settings->set('notifications/email', false);
$user->settings = $settings->toArray();
$user->save();
```

Writes from requests should go through a whitelist of allowed paths (see
[Security Guide](Security-Guide#writing-with-user-supplied-paths)).

## 6. Queues and Octane

- Create a new instance per job or request; do not store instances in static
  properties or singletons that outlive a request.
- The optional `middleware.memory_limit` guard measures the **whole worker
  process**. Leave it unset in long-running workers, or set it above the worker's
  normal peak.

## 7. Testing

```php
public function test_quantities_are_cast_to_integers(): void
{
    $payload = OrderPayload::fromJson('{"order":{"id":"7","items":[{"sku":"AB-1","qty":"2"}]}}');

    $this->assertSame(7, $payload->id());
    $this->assertSame([2], $payload->quantities());
}

public function test_invalid_documents_are_rejected(): void
{
    $this->expectException(InvalidPayload::class);
    OrderPayload::fromJson('{"order":{"id":"x"}}');
}
```

## Laravel validation or StrObj?

Use Laravel's validator for form requests and database-aware rules (`exists`,
`unique`). Use StrObj when you work with deeply nested JSON documents (webhooks,
third-party API responses, JSON columns), need path-based reads with explicit
defaults, or need the same logic outside the framework.
