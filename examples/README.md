# StrObj usage examples

Two interactive browser examples demonstrate the real library API. Path access,
defaults, `has()`, filters and validation rules replace nested `if`/`isset`
chains for data access. Ordinary PHP handles the forms, HTTP and persistence.

Both examples explicitly use `StringObjects::consistent()` for missing-field
defaults, preserved false/null values and strict filtering/validation options.
Existing applications keep legacy behavior through `instance()`; see
[Compatibility](../docs/compatibility.md) before changing profiles.

## Start here

Requirements: PHP 7.4+, Composer dependencies and `pdo_sqlite` for product CRUD.
Run these commands from the repository root:

```bash
composer install
php -S localhost:8000 -t examples
```

Open **<http://localhost:8000/>**. The [landing page](index.php) links to both
examples, and every page has navigation back to the overview and the other demo.

- JSON table: <http://localhost:8000/01-json-table/>
- Product CRUD: <http://localhost:8000/02-crud/>

No frontend build or JavaScript framework is required.

## 1. Select a table from complex JSON

[customers.json](01-json-table/customers.json) contains profiles, contacts,
addresses, accounts, orders, products, shipments, preferences and audit data.
Some records omit email or addresses; others contain zero orders or an inactive
account. Expand **See the original JSON response** to inspect that structure.

Try the [table page](01-json-table/index.php):

1. The first table shows just customer name, city and order count. Dana's missing
   address displays a dash, while her zero order count is preserved.
2. Select **Phone** and **Latest shipment**, then click **Apply columns and filters**.
   The second table gains those columns using their configured paths.
3. Change the city, account status or minimum spend. The third table shows your
   matching records and count. Defaults select Alice and Charlie: active Chicago
   customers with at least 2,000 USD in lifetime spending.
4. Try **All cities**, **All accounts**, and a minimum of **0**. Four customers
   match; Ethan's negative spending is rejected and he is excluded.
5. Click **Reset view** to restore the initial selection.

To introduce another field, add one definition to `$optionalColumns`. The picker,
table-building loop and renderer already work from those definitions:

```php
'Phone' => ['path' => 'profile/contact/phone', 'default' => '—'],
'Latest shipment' => [
    'path' => 'orders/0/fulfillment/shipment/tracking/status',
    'default' => '—',
],
```

The shared table helpers are in [table.php](01-json-table/table.php); HTML
escaping is shared with the layout and CRUD example.

Missing intermediate branches need no manual checks:

```php
$source->get('payload/customers/3/profile/addresses/billing/city', '—');
$source->get('payload/customers/*/profile/fullName'); // Every customer's name.
```

Wildcard **value filters** cast order counts, spending and account status. The
spending predicate rejects negative values, so `get()` returns the column's
default (`null`), displayed as “Rejected”. Stored `false` and `null` values are
always returned unchanged; use `has()` to tell a missing field from a rejected one.
The separate **row predicate** uses `get()` values with `array_filter()` to
exclude complete records. A value filter does not remove a row by itself.
Selected values are escaped before rendering HTML.

## 2. Create, read, update and delete products

The [CRUD page](02-crud/index.php) contains a real form and product table.
Start with an empty list; the page never creates or deletes records automatically.

1. Click **Use sample values**, or enter your own name, SKU, price and stock.
2. Click **Add product**. A success message appears and the new product is listed.
   Reload the page: the record remains and the form is not submitted again.
3. Click the row's **Edit** button. Its stored values populate the form. Change
   the price, stock, warehouse or status and click **Save changes**.
4. Edit again, set a negative price and change the city. Saving shows a message
   next to Price and keeps your inputs so you can correct them. Neither change
   reaches the stored record. Fix the price and save again.
5. Try stock **0** and status **Inactive**. These values are saved accurately.
   An empty description is stored as `null`.
6. Expand **Inspect the stored records as JSON** to see the nested data and types.
7. Click **Delete** on a row to remove that product. **Cancel edit** leaves it intact.

Name, SKU, price, currency, stock and status are validated by the service.
Warehouse and description are optional. Field errors come from StrObj validation;
the form deliberately lets the server show invalid input rather than relying
only on browser constraints.

### Persistence and isolation

SQLite databases and PHP sessions are stored under the already ignored
`build/examples/crud/`, outside the `examples/` document root. Each browser session
has its own database. Records survive reloads and server restarts while that
session remains available. A fresh browser session starts with an empty list.
The directory must be writable. To reset all local demo data, stop the server
and remove `build/examples/crud/`; this deletes all demo products and sessions.

Successful writes redirect back to the list. Mutations use POST with a session
form token. The example is a local learning tool with no account system.

### How the code is divided

- [index.php](02-crud/index.php): the form, table and HTTP actions.
- [web.php](02-crud/web.php): session/database setup, form path names and readable errors.
- [ProductService.php](02-crud/ProductService.php): writable paths, defaults,
  validation, type casts and partial updates using StrObj.
- [ProductRepository.php](02-crud/ProductRepository.php): prepared PDO statements.

```php
$created = $products->create($requestJson);
$record = $products->read($created['id']);
$updated = $products->update($created['id'], $patchJson);
$products->delete($created['id']);
```

The service ignores unknown fields. Missing values get creation defaults;
missing required fields fail validation. Partial updates use `has()` to preserve
omitted fields while accepting explicit `false`, `0` and `null`. The complete
candidate is validated before persistence. Cast `get()` values are copied to a
separate document because `toJson()` exports raw stored data.

To add a writable field, extend `FIELDS` and the necessary validation/cast rules
in `options()`. The service loops need no new nested existence checks.

## CLI demonstration and verification

The original scripted CRUD flow remains available separately. It uses a fresh
in-memory SQLite database and prints JSON without changing browser records:

```bash
php examples/02-crud/demo.php
php examples/01-json-table/index.php > table.html
```

HTTP integration tests exercise navigation, column selection, filtering,
creation, reload persistence, editing, rejected updates, deletion, escaping and
browser-session isolation:

```bash
composer test:integration
```
