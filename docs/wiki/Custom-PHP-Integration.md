# Custom PHP Integration

StrObj works in any PHP 7.4+ application. This page shows a plain PHP endpoint,
a PSR-15 middleware for PSR-7 frameworks (Slim, Mezzio and others), Symfony
controllers and command-line scripts.

## Plain PHP JSON endpoint

```php
<?php

declare(strict_types=1);

use StrObj\StringObjects;

require __DIR__ . '/../vendor/autoload.php';

const MAX_BODY_BYTES = 1048576; // 1 MiB

function respond(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['message' => 'Use POST.']);
    return;
}

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    respond(415, ['message' => 'Send application/json.']);
    return;
}

// Read at most one byte more than allowed, so oversized bodies are detected
// without loading them completely (CWE-400).
$body = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);

if ($body === false || strlen($body) > MAX_BODY_BYTES) {
    respond(413, ['message' => 'The request body is too large.']);
    return;
}

try {
    $contact = StringObjects::instance($body, [
        'validation' => ['rules' => [
            ['path' => 'name', 'pattern' => '/^.{2,80}$/u', 'required' => true],
            ['path' => 'email', 'pattern' => '/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', 'required' => true],
            ['path' => 'newsletter', 'pattern' => '/^[01]$/'],
        ]],
        'filters' => ['newsletter' => ['type' => 'bool']],
    ]);
} catch (InvalidArgumentException $exception) {
    respond(400, ['message' => 'The request body must be a JSON object.']);
    return;
}

if (!$contact->isValid()) {
    respond(422, ['message' => 'Check the name and e-mail address.']);
    return;
}

$name = $contact->get('name');
$email = $contact->get('email');
$newsletter = $contact->get('newsletter', false); // false when missing

// Store the contact with prepared statements, then respond.
respond(201, ['saved' => true]);
```

When you print values into HTML, escape them:
`htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. StrObj returns
data unchanged; output encoding is the application's job (CWE-79).

## PSR-15 middleware (Slim, Mezzio and other PSR-7 frameworks)

Parse the body once and pass the document to handlers as a request attribute:

```php
<?php

declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use StrObj\StringObjects;

final class JsonDocumentMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'document';

    private ResponseFactoryInterface $responses;
    private int $maxBytes;

    public function __construct(ResponseFactoryInterface $responses, int $maxBytes = 1048576)
    {
        $this->responses = $responses;
        $this->maxBytes = $maxBytes;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $stream = $request->getBody();
        $body = '';

        // read() may return fewer bytes than requested, so read until EOF or the limit.
        while (!$stream->eof() && strlen($body) <= $this->maxBytes) {
            $body .= $stream->read($this->maxBytes + 1 - strlen($body));
        }

        if (strlen($body) > $this->maxBytes) {
            return $this->responses->createResponse(413);
        }

        try {
            $document = StringObjects::instance($body);
        } catch (InvalidArgumentException $exception) {
            return $this->responses->createResponse(400);
        }

        return $handler->handle($request->withAttribute(self::ATTRIBUTE, $document));
    }
}
```

In a handler:

```php
/** @var StringObjects $document */
$document = $request->getAttribute(JsonDocumentMiddleware::ATTRIBUTE);
$city = $document->get('shipping/address/city', '');
```

## Symfony

```php
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use StrObj\StringObjects;

public function __invoke(Request $request): JsonResponse
{
    try {
        $payload = StringObjects::instance($request->getContent());
    } catch (\InvalidArgumentException $exception) {
        return new JsonResponse(['message' => 'Invalid JSON document.'], 400);
    }

    return new JsonResponse(['city' => $payload->get('shipping/address/city', null)]);
}
```

## Reading API responses

```php
$response = file_get_contents('https://api.example.com/orders/42'); // or your HTTP client
$order = StringObjects::instance($response);

$total = $order->get('data/totals/grand', 0.0);
$skus = $order->get('data/lines/*/product/sku');
```

Treat third-party responses as untrusted input: validate them before use.

## Caching documents (Memcached, Redis, PSR-16)

StrObj does not cache documents or reads itself: reads already take time
proportional to the path depth, and they always reflect the current data.
To avoid fetching or building the same document repeatedly, cache the **JSON
document** in your application's cache and create a new instance from it:

```php
$memcached = new Memcached();
$memcached->addServer('127.0.0.1', 11211);

$json = $memcached->get('orders:42');

if (!is_string($json)) {
    // Use your HTTP client here.
    $json = file_get_contents('https://api.example.com/orders/42');
    $memcached->set('orders:42', $json, 300);
}

$order = StringObjects::instance($json, $options);
```

The same pattern works with any [PSR-16](https://www.php-fig.org/psr/psr-16/)
cache (Redis, APCu, files) and with Laravel's `Cache::remember()`:

```php
$json = $cache->get('orders:42'); // Psr\SimpleCache\CacheInterface

if (!is_string($json)) {
    $json = $client->fetchOrder(42);
    $cache->set('orders:42', $json, 300);
}

$order = StringObjects::instance($json, $options);
```

- Cache the document, not the `StringObjects` instance. An instance is cheap to
  rebuild, holds per-request configuration and can contain closures, which PHP
  cannot serialize.
- Validation runs when the instance is created, so cached data is checked again
  with the current rules.
- Do not build cache keys from unchecked request input, and do not share cached
  documents between users unless they are public.

## Command-line scripts and workers

```php
foreach ($queue->messages() as $message) {
    $job = StringObjects::instance($message->body());
    // One instance per message; nothing is shared between iterations.
}
```

Do not set a low `middleware.memory_limit` in long-running processes: the guard
compares the whole process's memory usage.

## Configuration files

Keep options in PHP files that return arrays:

```php
// config/payloads/contact.php
return [
    'validation' => ['rules' => [/* ... */]],
    'filters' => [/* ... */],
];
```

```php
$options = require __DIR__ . '/../config/payloads/contact.php';
$contact = StringObjects::instance($body, $options);
```

Never load options from user-controlled files or request data; see the
[Security Guide](Security-Guide).
