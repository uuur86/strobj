<?php

declare(strict_types=1);

namespace StrObj\Examples\Crud;

use InvalidArgumentException;
use PDO;
use StrObj\StringObjects;

/** Starts an isolated browser session and opens its persistent SQLite database. */
function openDemoDatabase(): PDO
{
    // Store runtime files outside the examples document root, under ignored build/.
    // The optional override gives HTTP tests their own isolated runtime directory.
    $storage = getenv('STROBJ_EXAMPLE_STORAGE') ?: dirname(__DIR__, 2) . '/build/examples/crud';
    $sessions = $storage . '/sessions';

    if (!is_dir($sessions) && !mkdir($sessions, 0770, true) && !is_dir($sessions)) {
        throw new DemoStorageException('Cannot create the example storage directory.');
    }

    session_save_path($sessions);
    session_name('strobj_crud_demo');
    // Plain-HTTP local demos need the session cookie; HTTPS requests mark it Secure.
    session_set_cookie_params([ // NOSONAR: the secure flag follows the request scheme.
        'path' => '/',
        'secure' => isSecureRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        throw new DemoStorageException('Cannot start the example session.');
    }

    $_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));

    return new PDO('sqlite:' . $storage . '/' . hash('sha256', session_id()) . '.sqlite');
}

/** Reports whether the browser reached this page over HTTPS, so the session cookie can require it. */
function isSecureRequest(): bool
{
    $https = $_SERVER['HTTPS'] ?? '';

    return $https !== '' && strtolower((string) $https) !== 'off';
}

/** Rejects writes that do not originate from the current browser's form. */
function requireFormToken(StringObjects $request): void
{
    $token = $request->get('csrf', '');

    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        throw new InvalidArgumentException('This form expired. Reload the page and try again.');
    }
}

/** @param mixed $value Raw request ID; only positive integers are accepted. */
function requireProductId($value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($id === false) {
        throw new InvalidArgumentException('Choose a valid product from the list.');
    }

    return $id;
}

/** Builds the nested request; an empty description explicitly clears its value. */
function requestProductJson(StringObjects $request): string
{
    $payload = StringObjects::instance(['product' => $request->get('product', [])]);

    if ($payload->get('product/description') === '') {
        $payload->set('product/description', null);
    }

    return $payload->toJson();
}

/** Converts a slash path to the HTML name that PHP decodes into nested arrays. */
function formFieldName(string $path): string
{
    $segments = explode('/', $path);

    return array_shift($segments) . '[' . implode('][', $segments) . ']';
}

/** Returns the aria-invalid value of a field that may have a validation message. */
function invalidState(array $errors, string $path): string
{
    return array_key_exists($path, $errors) ? 'true' : 'false';
}

/** Safely displays form values, including false/zero, without traversing branches. */
function formValue(StringObjects $form, string $path, string $default = ''): string
{
    $value = $form->get($path, $default);

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return is_scalar($value) ? (string) $value : '';
}

/** Maps the service's invalid paths to clear messages next to the relevant fields. */
function validationMessages(string $message): array
{
    $prefix = 'Invalid fields: ';

    if (strpos($message, $prefix) !== 0) {
        return [];
    }

    $messages = [
        'product/name' => 'Use a name between 2 and 80 characters.',
        'product/sku' => 'Use 3–30 uppercase letters, digits or hyphens.',
        'product/pricing/amount' => 'Price must be zero or more, with up to two decimal places.',
        'product/pricing/currency' => 'Choose USD, EUR or GBP.',
        'product/inventory/stock' => 'Stock must be a whole number, zero or more.',
        'product/inventory/warehouse/city' => 'Use no more than 80 characters for the city.',
        'product/publication/active' => 'Choose Active or Inactive.',
        'product/description' => 'Use no more than 500 characters for the description.',
    ];

    return array_intersect_key($messages, array_flip(explode(', ', substr($message, strlen($prefix)))));
}
