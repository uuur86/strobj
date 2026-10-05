<?php

declare(strict_types=1);

use function StrObj\Examples\renderFooter;
use function StrObj\Examples\renderHeader;

require_once __DIR__ . '/layout.php';

renderHeader('Explore the library', 'home', './');
?>
<div class="hero">
    <p class="eyebrow">Learn by trying</p>
    <h1>Nested data, simpler code.</h1>
    <p>Explore two working examples of StrObj. Choose fields from a complex JSON response,
        then manage products through a real create, read, update and delete workflow.</p>
</div>

<div class="cards">
    <section class="panel">
        <p class="eyebrow">01 · JSON table</p>
        <h2>Choose the data you want to see.</h2>
        <p>A customer response contains profiles, addresses, orders and products.
            Select columns and change the filters to build a table from just the values you need.</p>
        <pre><code>$data->get('payload/customers/0/profile/fullName');
$data->get('payload/customers/3/profile/contact/email', '—');</code></pre>
        <ul>
            <li>Add phone and shipment columns with the column picker.</li>
            <li>Filter by city, account status and spending.</li>
            <li>See defaults for missing branches and rejected negative values.</li>
        </ul>
        <a class="button" href="01-json-table/">Explore the JSON table →</a>
    </section>
    <section class="panel">
        <p class="eyebrow">02 · Product CRUD</p>
        <h2>Create it. Edit it. Delete it.</h2>
        <p>Add a product using the form and see it appear in the list. Edit its price or stock,
            try an invalid value, and delete the record when you are done.</p>
        <pre><code>$products->create($requestJson);
$products->update($id, $patchJson);
$products->delete($id);</code></pre>
        <ul>
            <li>Your records remain available when you reload the page.</li>
            <li>StrObj validates the complete record before a write.</li>
            <li>Zero stock and an inactive status are preserved.</li>
        </ul>
        <a class="button" href="02-crud/">Try product CRUD →</a>
    </section>
</div>

<section class="panel">
    <h2>One starting point</h2>
    <p>Start the local server from the repository root, then open this page:</p>
    <pre><code>composer install
php -S localhost:8000 -t examples
# Open http://localhost:8000/</code></pre>
    <p class="muted">PHP 7.4+ is required. The CRUD example also needs <code>pdo_sqlite</code>.
        Full instructions and a walkthrough are in <code>examples/README.md</code>.</p>
</section>
<?php renderFooter(); ?>
