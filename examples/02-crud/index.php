<?php

declare(strict_types=1);

use StrObj\Examples\Crud\ProductRepository;
use StrObj\Examples\Crud\ProductService;
use StrObj\StringObjects;

use function StrObj\Examples\escapeHtml;
use function StrObj\Examples\renderFooter;
use function StrObj\Examples\renderHeader;
use function StrObj\Examples\Crud\formFieldName;
use function StrObj\Examples\Crud\formValue;
use function StrObj\Examples\Crud\invalidState;
use function StrObj\Examples\Crud\openDemoDatabase;
use function StrObj\Examples\Crud\requestProductJson;
use function StrObj\Examples\Crud\requireFormToken;
use function StrObj\Examples\Crud\requireProductId;
use function StrObj\Examples\Crud\validationMessages;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/layout.php';
require_once __DIR__ . '/ProductRepository.php';
require_once __DIR__ . '/ProductService.php';
require_once __DIR__ . '/DemoStorageException.php';
require_once __DIR__ . '/web.php';

if (!class_exists(PDO::class) || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    http_response_code(503);
    renderHeader('Product CRUD setup', 'crud');
    echo '<section class="panel"><h1>Enable SQLite to try product CRUD</h1>',
    '<p>This example needs PHP’s <code>pdo_sqlite</code> extension. Enable it in your PHP configuration, ',
    'restart the local server, and reload this page.</p>',
    '<a class="button secondary" href="../">Back to examples</a></section>';
    renderFooter();

    return;
}

$products = new ProductService(new ProductRepository(openDemoDatabase()));
$request = StringObjects::instance($_POST);
$query = StringObjects::instance($_GET);
$form = StringObjects::instance(['product' => ['pricing' => ['currency' => 'USD'],
    'inventory' => ['stock' => 0], 'publication' => ['active' => false]]]);
$editId = null;
$errors = [];
$errorMessage = '';
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);

// The controller handles HTTP. ProductService handles paths, defaults and validation.
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $form = StringObjects::instance(['product' => $request->get('product', [])]);
        requireFormToken($request);

        switch ($request->get('action')) {
            case 'create':
                $created = $products->create(requestProductJson($request));
                $_SESSION['notice'] = sprintf('Product #%d created. It is now in your list.', $created['id']);
                break;
            case 'update':
                $editId = requireProductId($request->get('id'));
                $products->update($editId, requestProductJson($request));
                $_SESSION['notice'] = sprintf('Product #%d updated.', $editId);
                break;
            case 'delete':
                $id = requireProductId($request->get('id'));
                $products->delete($id);
                $_SESSION['notice'] = sprintf('Product #%d deleted.', $id);
                break;
            default:
                throw new InvalidArgumentException('Choose an action using the product form or list.');
        }

        // Redirect after a successful write: refreshing does not repeat the operation.
        header('Location: ./', true, 303);
        exit;
    }

    if ($query->has('edit')) {
        $editId = requireProductId($query->get('edit'));
        $form = StringObjects::instance($products->read($editId));
    }

    if ($query->has('sample') && $editId === null) {
        $form = StringObjects::instance(['product' => [
            'name' => 'Wireless Headphones', 'sku' => 'HEADSET-01',
            'pricing' => ['amount' => '149.90', 'currency' => 'USD'],
            'inventory' => ['stock' => 0, 'warehouse' => ['city' => 'Chicago']],
            'publication' => ['active' => false],
            'description' => 'A sample product. Try changing its price and stock.',
        ]]);
    }
} catch (InvalidArgumentException | OutOfBoundsException | JsonException $exception) {
    $errorMessage = $exception->getMessage();
    $errors = validationMessages($errorMessage);
    $errorMessage = $errors === [] ? $errorMessage : 'Please correct the highlighted fields. Nothing has been saved.';
    http_response_code($exception instanceof OutOfBoundsException ? 404 : 422);
}

$records = $products->all();
$csrf = $_SESSION['csrf'];
session_write_close();

// Each input's name is a nested path. PHP builds the payload and StrObj reads it.
$textFields = [
    'product/name' => ['label' => 'Product name', 'type' => 'text', 'hint' => '2–80 characters.', 'full' => true],
    'product/sku' => [
        'label' => 'SKU', 'type' => 'text', 'hint' => 'Uppercase letters, digits and hyphens.', 'full' => true,
    ],
    'product/pricing/amount' => [
        'label' => 'Price', 'type' => 'number', 'step' => '0.01',
        'hint' => 'Zero or more; up to 2 decimal places.', 'full' => false,
    ],
    'product/inventory/stock' => [
        'label' => 'Stock', 'type' => 'number', 'step' => '1',
        'hint' => 'Zero is a valid stock level.', 'full' => false,
    ],
    'product/inventory/warehouse/city' => [
        'label' => 'Warehouse city', 'type' => 'text',
        'hint' => 'Optional. Missing branches are created for you.', 'full' => true,
    ],
];
// Fields rendered outside the text field loop.
$currencyPath = 'product/pricing/currency';
$activePath = 'product/publication/active';
$descriptionPath = 'product/description';

renderHeader('Product CRUD', 'crud');
?>
<div class="hero">
    <p class="eyebrow">02 · Create, read, update, delete</p>
    <h1>Try the complete product workflow.</h1>
    <p>Add a product, see it in the list, then edit or delete it. Your records survive page reloads
        and are separate from other browser sessions.</p>
</div>
<?php if ($notice !== '') : ?>
    <output class="alert success"><?= escapeHtml($notice) ?></output>
<?php endif; ?>
<?php if ($errorMessage !== '') : ?>
    <div class="alert error" role="alert"><?= escapeHtml($errorMessage) ?></div>
<?php endif; ?>

<div class="crud-grid">
    <section class="panel" aria-labelledby="form-title">
        <div class="panel-heading">
            <div><h2 id="form-title"><?= $editId === null ? 'Add a product' : 'Edit product #' . $editId ?></h2>
                <p class="muted"><?= $editId === null
                    ? 'Start with your own values or load the sample.'
                    : 'Your changes are checked before they are saved.' ?></p></div>
        </div>
        <form action="./" method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= escapeHtml($csrf) ?>">
            <input type="hidden" name="action" value="<?= $editId === null ? 'create' : 'update' ?>">
            <input type="hidden" name="id" value="<?= $editId ?? '' ?>">
            <div class="form-grid">
                <?php foreach ($textFields as $path => $field) :
                    $fieldId = escapeHtml(str_replace('/', '-', $path)); ?>
                    <div class="field <?= $field['full'] ? 'full' : '' ?>">
                        <label for="<?= $fieldId ?>"><?= escapeHtml($field['label']) ?></label>
                        <input id="<?= $fieldId ?>" name="<?= escapeHtml(formFieldName($path)) ?>"
                            type="<?= escapeHtml($field['type']) ?>" value="<?= escapeHtml(formValue($form, $path)) ?>"
                            <?= isset($field['step']) ? 'min="0" step="' . escapeHtml($field['step']) . '"' : '' ?>
                            aria-invalid="<?= invalidState($errors, $path) ?>"
                            aria-describedby="<?= $fieldId ?>-hint <?= $fieldId ?>-error">
                        <span class="hint" id="<?= $fieldId ?>-hint"><?= escapeHtml($field['hint']) ?></span>
                        <span class="field-error" id="<?= $fieldId ?>-error"><?=
                            escapeHtml($errors[$path] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="field"><label for="currency">Currency</label>
                    <select id="currency" name="product[pricing][currency]"
                        aria-invalid="<?= invalidState($errors, $currencyPath) ?>"
                        aria-describedby="currency-error">
                        <?php foreach (['USD', 'EUR', 'GBP'] as $currency) : ?>
                            <option value="<?= $currency ?>" <?=
                                formValue($form, $currencyPath, 'USD') === $currency ? 'selected' : ''
                            ?>><?= $currency ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" id="currency-error"><?=
                        escapeHtml($errors[$currencyPath] ?? '') ?></span>
                </div>
                <div class="field"><label for="active">Status</label>
                    <select id="active" name="product[publication][active]"
                        aria-invalid="<?= invalidState($errors, $activePath) ?>"
                        aria-describedby="active-error">
                        <option value="0" <?=
                            formValue($form, $activePath, '0') === '0' ? 'selected' : ''
                        ?>>Inactive</option>
                        <option value="1" <?=
                            formValue($form, $activePath, '0') === '1' ? 'selected' : ''
                        ?>>Active</option>
                    </select>
                    <span class="field-error" id="active-error"><?=
                        escapeHtml($errors[$activePath] ?? '') ?></span>
                </div>
                <div class="field full"><label for="description">Description</label>
                    <textarea id="description" name="product[description]" rows="3"
                        aria-invalid="<?= invalidState($errors, $descriptionPath) ?>"
                        aria-describedby="description-hint description-error"><?=
                            escapeHtml(formValue($form, $descriptionPath)) ?></textarea>
                    <span class="hint" id="description-hint">Optional. Leave empty to store a null description.</span>
                    <span class="field-error" id="description-error"><?=
                        escapeHtml($errors[$descriptionPath] ?? '') ?></span>
                </div>
                <div class="actions full">
                    <button type="submit"><?= $editId === null ? 'Add product' : 'Save changes' ?></button>
                    <a class="button secondary" href="<?= $editId === null ? '?sample=1' : './' ?>"><?=
                        $editId === null ? 'Use sample values' : 'Cancel edit' ?></a>
                </div>
            </div>
        </form>
    </section>

    <section class="panel" aria-labelledby="list-title">
        <div class="panel-heading">
            <div><h2 id="list-title">Your products</h2>
                <p class="muted">Edit fills the form with the stored record. Delete removes it immediately.</p></div>
            <span class="badge"><?= count($records) ?> <?= count($records) === 1 ? 'product' : 'products' ?></span>
        </div>
        <div class="table-scroll">
            <table class="product-table">
                <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Warehouse</th><th>Status</th>
                    <th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($records === []) :
                        ?><tr><td class="empty" colspan="6">No products yet. Add one using the form.</td></tr><?php
                    endif; ?>
                    <?php foreach ($records as $record) :
                        $item = StringObjects::instance($record);
                        $recordId = (int) $record['id'];
                        $name = escapeHtml($item->get('product/name'));
                        $active = (bool) $item->get($activePath);
                        $warehouseCity = $item->get('product/inventory/warehouse/city'); ?>
                        <tr data-product-id="<?= $recordId ?>">
                            <td><strong><?= $name ?></strong>
                                <span class="hint"><?= escapeHtml($item->get('product/sku')) ?> ·
                                    #<?= $recordId ?></span></td>
                            <td data-label="Price"><?= escapeHtml($item->get($currencyPath)) ?> <?=
                                number_format($item->get('product/pricing/amount'), 2, '.', ',') ?></td>
                            <td data-label="Stock"><?= (int) $item->get('product/inventory/stock') ?></td>
                            <td data-label="Warehouse"><?=
                                escapeHtml($warehouseCity === '' ? '—' : $warehouseCity) ?></td>
                            <td data-label="Status"><span class="status <?= $active ? 'active' : '' ?>"><?=
                                $active ? 'Active' : 'Inactive' ?></span></td>
                            <td><div class="actions">
                                <a class="button secondary small" href="?edit=<?= $recordId ?>"
                                    aria-label="Edit <?= $name ?>">Edit</a>
                                <form action="./" method="post">
                                    <input type="hidden" name="csrf" value="<?= escapeHtml($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $recordId ?>">
                                    <button class="danger small" type="submit"
                                        aria-label="Delete <?= $name ?>"
                                        >Delete</button>
                                </form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($records !== []) : ?>
            <details><summary>Inspect the stored records as JSON</summary>
                <pre><code><?= escapeHtml(json_encode(
                    $records,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                )) ?></code></pre>
            </details>
        <?php endif; ?>
        <details open><summary>Try a validation failure</summary>
            <p class="muted">Edit a product, enter a negative price and change the city. Submit the form.
                StrObj rejects the candidate, so neither change reaches the list. Correct the price and save again.</p>
        </details>
    </section>
</div>

<section class="panel">
    <h2>What StrObj simplifies here</h2>
    <div class="explain-grid">
        <div><h3>Read nested values and apply defaults</h3>
            <p>Form inputs become a nested payload. One path lookup handles missing intermediate branches.</p>
            <pre><code>$request = StringObjects::instance($json);
$stock = $request->get('product/inventory/stock', 0);
$city = $request->get('product/inventory/warehouse/city', '');</code></pre>
        </div>
        <div><h3>Update only supplied fields</h3>
            <p>The service's partial-update loop preserves omitted fields and accepts explicit zero, false and null.</p>
            <pre><code>foreach ($writablePaths as $path) {
    if (!$patch->has($path)) {
        continue;
    }
    $candidate->set($path, $patch->get($path));
}</code></pre>
        </div>
    </div>
    <p class="muted"><code>ProductService.php</code> selects writable fields, validates the full candidate,
        and casts accepted values. <code>ProductRepository.php</code> persists them with prepared SQL.
        The browser form, session and redirects are ordinary PHP.</p>
</section>
<?php renderFooter(); ?>
