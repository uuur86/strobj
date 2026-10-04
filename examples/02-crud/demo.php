<?php

declare(strict_types=1);

use StrObj\Examples\Crud\ProductRepository;
use StrObj\Examples\Crud\ProductService;
use StrObj\StringObjects;

require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/ProductRepository.php';
require __DIR__ . '/ProductService.php';

// Real SQL operations in a fresh in-memory database; no files are written.
$products = new ProductService(new ProductRepository(new PDO('sqlite::memory:')));

// CREATE: defaults for missing currency/city; false, zero and null are preserved.
$created = $products->create(<<<'JSON'
{
  "product": {
    "name": "Wireless Headphones",
    "sku": "HEADSET-01",
    "pricing": {"amount": "1499.90"},
    "inventory": {"stock": 0},
    "publication": {"active": false},
    "description": null,
    "internalCost": 100
  },
  "context": {"requestId": "demo-001"}
}
JSON
);
$id = $created['id'];

// READ: access nested data without chains of if/isset checks.
$read = $products->read($id);
$view = StringObjects::consistent($read);
$selected = [
    'name' => $view->get('product/name'),
    'city' => $view->get('product/inventory/warehouse/city'),
    'stock' => $view->get('product/inventory/stock'),
    'active' => $view->get('product/publication/active'),
];

// UPDATE: only supplied paths change; SKU, currency and description are preserved.
$updated = $products->update($id, <<<'JSON'
{
  "product": {
    "pricing": {"amount": "1099.50"},
    "inventory": {"stock": "15", "warehouse": {"city": "Boston"}},
    "publication": {"active": true}
  }
}
JSON
);

// Two-field update: the invalid price also prevents the city change from being saved.
$rejected = '';

try {
    $products->update($id, '{"product":{"pricing":{"amount":"-10"},"inventory":{"warehouse":{"city":"Seattle"}}}}');
} catch (InvalidArgumentException $exception) {
    $rejected = $exception->getMessage();
}

$afterRejectedUpdate = $products->read($id);

// DELETE: the record is gone; one domain exception handles a subsequent read.
$products->delete($id);
$notFound = '';

try {
    $products->read($id);
} catch (OutOfBoundsException $exception) {
    $notFound = $exception->getMessage();
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    '1_create' => $created,
    '2_read_selected_fields' => $selected,
    '3_update' => $updated,
    '4_rejected_update' => ['error' => $rejected, 'stored_record' => $afterRejectedUpdate],
    '5_delete' => ['remaining_records' => $products->all(), 'read_error' => $notFound],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
