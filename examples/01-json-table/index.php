<?php

declare(strict_types=1);

use StrObj\StringObjects;

use function StrObj\Examples\renderFooter;
use function StrObj\Examples\renderHeader;
use function StrObj\Examples\escapeHtml;
use function StrObj\Examples\JsonTable\buildRows;
use function StrObj\Examples\JsonTable\renderTable;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/layout.php';
require_once __DIR__ . '/table.php';

$source = StringObjects::instance(file_get_contents(__DIR__ . '/customers.json'), [
    'filters' => [
        'payload/customers/*/account/active' => ['type' => 'bool'],
        'payload/customers/*/commerce/summary/orderCount' => ['type' => 'int'],
        'payload/customers/*/commerce/summary/lifetimeSpend' => [
            'type' => 'float',
            'callback' => static function (float $value): bool {
                return $value >= 0;
            },
        ],
    ],
]);

// This list defines exactly which values appear in the table.
$columns = [
    'Customer' => ['path' => 'profile/fullName', 'default' => 'Unnamed customer'],
    'City' => ['path' => 'profile/addresses/billing/city', 'default' => '—'],
    'Orders' => ['path' => 'commerce/summary/orderCount', 'default' => 0],
];

// Add columns without traversing the JSON manually or changing the renderer.
$optionalColumns = [
    'Email' => ['path' => 'profile/contact/email', 'default' => '—'],
    'Lifetime spend' => [
        // A value rejected by the spending filter returns the default instead of the value.
        'path' => 'commerce/summary/lifetimeSpend', 'default' => null,
        'format' => static function (?float $value): string {
            return $value === null ? 'Rejected' : number_format($value, 2, '.', ',') . ' USD';
        },
    ],
    'Latest product SKU' => ['path' => 'orders/0/items/0/product/sku', 'default' => '—'],
    'Active' => [
        'path' => 'account/active', 'default' => false,
        'format' => static function (bool $value): string {
            return $value ? 'Yes' : 'No';
        },
    ],
    'Phone' => ['path' => 'profile/contact/phone', 'default' => '—'],
    'Latest shipment' => ['path' => 'orders/0/fulfillment/shipment/tracking/status', 'default' => '—'],
];

// Query parameters control the view, while the selected paths define its shape.
$query = StringObjects::instance($_GET);
$defaultColumns = $query->has('configured') ? [] : ['Email', 'Lifetime spend', 'Latest product SKU', 'Active'];
$chosenColumns = array_filter((array) $query->get('columns', $defaultColumns), 'is_string');
$extendedColumns = $columns + array_intersect_key($optionalColumns, array_flip($chosenColumns));
$city = $query->get('city', 'Chicago');
$selectedCity = in_array($city, ['', 'Chicago', 'Denver'], true) ? $city : 'Chicago';
$minimumInput = filter_var($query->get('minimumSpend', 2000), FILTER_VALIDATE_FLOAT);
$minimumSpend = $minimumInput === false ? 2000 : max(0, $minimumInput);
$activeOnly = $query->get('active', '1') === '1';

// A value predicate rejects a value (get() returns the default); array_filter excludes a whole row.
// get() handles missing intermediate branches without nested existence checks.
$acceptCustomer = static function ($index) use ($source, $selectedCity, $minimumSpend, $activeOnly): bool {
    $path = 'payload/customers/' . $index;
    $spendPath = $path . '/commerce/summary/lifetimeSpend';
    // A missing value counts as zero; has() tells it apart from a rejected value.
    $spend = $source->has($spendPath) ? $source->get($spendPath, null) : 0.0;

    return (!$activeOnly || $source->get($path . '/account/active', false) === true)
    && ($selectedCity === '' || $source->get($path . '/profile/addresses/billing/city', '') === $selectedCity)
    && $spend !== null
    && $spend >= $minimumSpend;
};

$basicRows = buildRows($source, $columns);
$extendedRows = buildRows($source, $extendedColumns);
$filteredRows = buildRows($source, $extendedColumns, $acceptCustomer);
$names = $source->get('payload/customers/*/profile/fullName');

header('Content-Type: text/html; charset=UTF-8');
renderHeader('JSON table', 'table');
?>
<div class="hero">
    <p class="eyebrow">01 · Complex JSON → selected columns</p>
    <h1>Complex data. Just the columns you need.</h1>
    <p>Start with a simple table, select more fields and change the row filters.
        Every table uses the same nested customer response. No manual branch checks are needed.</p>
</div>

    <section class="panel">
        <h2>1. Start with three selected fields</h2>
        <p>Customer name, billing city and order count. Dana has no address: the default displays a dash.
            Her order count is still zero.</p>
        <?php renderTable($columns, $basicRows); ?>
        <details><summary>See the original JSON response</summary>
            <pre><code><?= escapeHtml(file_get_contents(__DIR__ . '/customers.json')) ?></code></pre>
        </details>
    </section>

    <section class="panel">
        <h2>2. Add columns and choose your filters</h2>
        <p>Check Phone or Latest shipment, then apply. The same renderer reads the selected paths.
            Negative spending is rejected by the value filter and appears as “Rejected”.</p>
        <form action="./" method="get">
            <input type="hidden" name="configured" value="1">
            <fieldset><legend>Extra columns</legend><div class="column-picker">
                <?php foreach ($optionalColumns as $title => $column) : ?>
                    <label><input type="checkbox" name="columns[]" value="<?= escapeHtml($title) ?>"
                    <?= array_key_exists($title, $extendedColumns) ? 'checked' : '' ?>>
                    <?= escapeHtml($title) ?><code><?= escapeHtml($column['path']) ?></code></label>
                <?php endforeach; ?>
            </div></fieldset>
            <div class="filters">
                <div class="field"><label for="city">Billing city</label><select id="city" name="city">
                    <?php foreach (
                        ['' => 'All cities', 'Chicago' => 'Chicago', 'Denver' => 'Denver'] as $value => $label
) :
    ?>
                        <option value="<?= escapeHtml($value) ?>"
                        <?= $selectedCity === $value ? 'selected' : '' ?>><?= escapeHtml($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
                <div class="field"><label for="active">Account status</label><select id="active" name="active">
                    <option value="1" <?= $activeOnly ? 'selected' : '' ?>>Active only</option>
                    <option value="0" <?= !$activeOnly ? 'selected' : '' ?>>All accounts</option>
                </select></div>
                <div class="field"><label for="minimumSpend">Minimum spend (USD)</label>
                    <input id="minimumSpend" name="minimumSpend" type="number" min="0" step="0.01"
                        value="<?= $minimumSpend ?>"></div>
            </div>
            <div class="actions"><button type="submit">Apply columns and filters</button>
                <a class="button secondary" href="./">Reset view</a></div>
        </form>
        <h3>Selected columns · all <?= count($extendedRows) ?> customers</h3>
        <?php renderTable($extendedColumns, $extendedRows); ?>
    </section>

    <section class="panel">
        <div class="panel-heading"><div><h2>3. See your filtered result</h2>
            <p><?= $activeOnly ? 'Active accounts' : 'All accounts' ?> ·
                <?= escapeHtml($selectedCity ?: 'All cities') ?> · at least
                <?= number_format($minimumSpend, 2, '.', ',') ?> USD in spending.</p></div>
            <span class="badge"><?= count($filteredRows) ?>/<?= count($basicRows) ?> customers</span></div>
        <?php renderTable($extendedColumns, $filteredRows); ?>
        <?php if ($filteredRows === []) :
            ?><p class="muted">No customers match. Try all cities, all accounts or a lower spending amount.</p><?php
        endif; ?>
    </section>

<section class="panel">
    <h2>What StrObj simplifies here</h2>
    <div class="explain-grid">
        <div><h3>Missing branches have a default</h3>
            <pre><code>$source->get(
    'payload/customers/3/profile/addresses/billing/city',
    '—'
); // Returns '—' even though addresses is null.</code></pre>
        </div>
        <div><h3>One definition adds a column</h3>
            <pre><code>'Phone' => [
    'path' => 'profile/contact/phone',
    'default' => '—',
],</code></pre>
        </div>
    </div>
    <p>A wildcard reads every customer's name with one query:</p>
    <pre><code>$source->get('payload/customers/*/profile/fullName');</code></pre>
    <p><?= escapeHtml(implode(' · ', $names)) ?></p>
</section>
<?php renderFooter(); ?>
