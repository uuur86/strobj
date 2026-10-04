# Testing and consistency

## Running the suite

PHP 7.4 or newer is required. Development tests use PHPUnit 9.6. Install the
development dependencies, then run:

```bash
composer install
composer test
composer test:unit
composer test:regression
composer test:integration
composer phpstan
```

`tests/bootstrap.php` uses Composer's autoloader when available. It can also load
the library directly when running an official standalone PHPUnit 9.6 PHAR.

## Coverage gate

With PCOV or Xdebug enabled:

```bash
# Set XDEBUG_MODE=coverage when using Xdebug.
composer test:coverage
```

With phpdbg, no additional coverage extension is needed:

```bash
composer test:coverage:phpdbg
# Or run the same commands directly:
phpdbg -qrr vendor/bin/phpunit --coverage-clover build/coverage/clover.xml --coverage-html build/coverage/html --coverage-text
php tools/check-coverage.php build/coverage/clover.xml
```

Reports are generated at `build/coverage/clover.xml` and
`build/coverage/html/index.html`. The gate checks all PHP files in `src/`,
including files with no executable statements such as interfaces. It fails if a
file is omitted, a statement is uncovered, the report is empty or malformed, or
its metrics disagree with the recorded lines. Coverage-ignore annotations are
disabled in `phpunit.xml`.

The required metric is **100% executable-line coverage**. The local phpdbg run
does not measure branch or path coverage. Boundary cases, both sides of important
conditions, exceptions and mutation sequences are tested explicitly. Full line
coverage shows that every executable line ran; it cannot prove that every possible
input or execution path works. The distinction follows the
[PHPUnit coverage documentation](https://docs.phpunit.de/en/9.6/code-coverage-analysis.html).

Type conversions use explicit conditions rather than switch labels, which phpdbg
reported as unvisited even while each cast's return statement was executed. Every
supported conversion and the unsupported-type exception have separate tests.

## Suites

| Suite | Responsibility |
| --- | --- |
| Unit | Paths, cache, snapshots, direct SPL mutations, adapters, validation, filters, memory guards and input errors |
| Regression | Reproduce observed failures: deep child writes (GH-10519), missing/existing fields, cached writes, wildcard validation, JSON list shape, path iterator mutation, clone isolation and six SPL sorting operations |
| Integration | Public facade workflows, independent instances, 180 deterministic writes compared with an independent array model, and coverage-gate success/failure cases |

The original four test classes remain in `tests/Unit/`. Their fixture paths now
work from the moved directory, and debug output is removed so strict output
checks can detect accidental library output.

PHPStan checks production code at level 5 with PHP 7.4 as the target. PHPDoc
types describe supported inputs, while runtime guards also reject invalid ones;
`treatPhpDocTypesAsCertain: false` keeps these defensive checks meaningful to the
analyzer. No static-analysis errors are suppressed. Inherited SPL sorting keeps
its native signatures; shared storage observation maintains cache consistency.

## Deep-write compatibility: PHP GH-10519

The SPL child-reference bug was fixed upstream in
[PHP 8.1.18](https://github.com/php/php-src/blob/php-8.1.18/NEWS) and
[PHP 8.2.5](https://github.com/php/php-src/blob/php-8.2.5/NEWS).
The library supports PHP 7.4 and newer, so deep writes cannot depend on that
upstream fix being available.

`DataObject::set()` uses `PathResolver::write()` to rebuild the complete changed
branch, then commits it at the first path segment. It does not write through
`RecursiveArrayIterator::getChildren()`. This approach avoids both the SPL bug
and the former workaround's accidental promotion of a nested key to the root.
The same algorithm runs on every supported PHP version, so neither a version
threshold nor a runtime feature probe is required.

For example, starting with `{"a":{"b":{"c":1}}}`, calling
`set('a/b/d/e', 5)` produces exactly `{"a":{"b":{"c":1,"d":{"e":5}}}}`.
It does not add a root `b` field or change an existing root `b` field.

`Gh10519RegressionTest` checks this case through both `StringObjects` and
`DataObject`, covering arrays, objects, JSON input, mixed containers, existing
root keys, numeric list indexes, preserved sibling rows and cached ancestor
reads. These tests run in the existing PHP 7.4 and 8.0–8.5 CI matrix.

```bash
composer test:regression -- --filter Gh10519RegressionTest
```

## Behavior contracts and compatibility changes

The contracts below describe the explicit **consistent** profile used by the new
examples and workflow tests. `StringObjects::instance()` retains legacy defaults;
the full profile comparison and preserved signatures are in
[Compatibility](compatibility.md). `CompatibilityTest` also validates the v2.1
API fixture and loads real subclasses using its original method declarations.

- Paths use `/`. Leading, trailing and repeated separators are normalized. `0`
  and Unicode keys are retained. A segment equal to `*` matches one level.
  Multiple wildcard levels return nested lists; missing concrete leaves remain
  `null` in projections. `get('')`, `get(null)` and `get('*')` return the root.
- `has()` checks the stored structure and treats existing `null`, `false` and
  zero values as present. Missing concrete fields return the caller's default
  (`false` by default in `StringObjects`). `DataObject::query()` returns `null`
  for a missing concrete field.
- `set()` creates missing branches, replaces a selected container completely,
  and turns scalar intermediates into arrays. Empty/root and wildcard write paths
  throw `InvalidArgumentException`. Invalid or cyclic writes leave stored data
  unchanged. All successful writes invalidate reads and validation results.
- Writable object state in consistent instances and cloned snapshot containers
  is copied.
  Changing a returned object does not update the library; use `set()` or explicit
  `DataObject` offset operations. Custom nested class types, methods, non-public
  state and JSON serializers are retained. Readonly/internal state follows native
  cloning; uncloneable handles/resources retain identity. Root Traversable inputs
  are read as key/value entries. Cycles in copied writable state and depth above
  512 are rejected. See the copying limitations in the compatibility guide.
- Arrays stay arrays; object containers stay objects for JSON output.
  `toArray()` recursively converts containers into arrays. JSON lists remain
  lists after contiguous writes. PHP's normal JSON handling for sparse numeric
  keys still applies. JSON encoding failures throw `JsonException`.
- No validation rules means valid. Rules inspect stored values before output
  filters. `required` defaults to `false`; it rejects missing values, `null` and
  empty strings when enabled. Zero and boolean values are checked as `0`/`1`,
  rather than bypassing validation. Containers fail scalar regex validation.
  Invalid regex patterns throw `UnexpectedValueException`. All results for a
  path and its parents combine with logical AND. Rules added through `setRules()`
  append; `setPatterns()` replaces named patterns. Both invalidate prior results.
- Filters run on the selected value's complete path. An exact path wins over
  less specific patterns; otherwise the pattern with most literal segments wins.
  Ties retain configuration order. Callbacks are predicates: accepted values
  retain the selected PHP cast; rejected values return `false`. String callables,
  method callables, invokable objects and closures are supported. Unknown cast
  types throw `InvalidArgumentException`; invalid JSON casts throw `JsonException`.
  `get()` applies filters to selected values; exports keep stored values.
  A subtree read does not recursively apply every descendant filter.
- `middleware.memory_limit` is in bytes; omission or `-1` disables the local
  guard. `setMemoryLimit()` accepts positive megabytes and can be updated
  repeatedly. PHP's `memory_limit=-1` is unlimited. A finite PHP limit caps the
  local setting without modifying php.ini. The guard checks current process
  memory at facade operations; it does not reserve memory or predict a pending
  allocation.

The behavior profile makes changes to defaults, copying, casts and configuration
explicit. Storage/validation bug fixes run in both profiles. The original docblocks
are retained and corrected to describe the implemented API; new members are
documented as well.

## CI and local verification

`.github/workflows/php.yml` configures the complete suite for PHP 7.4 and
8.0–8.5. A separate PHP 8.3 job uses PCOV and enforces the line-coverage gate.
Coverage reports are saved as CI artifacts. These jobs run when the changes are
pushed or included in a pull request; editing the workflow locally does not run
GitHub Actions. The setup follows the official
[setup-php instructions](https://github.com/shivammathur/setup-php).

Run the commands above to verify the current checkout. Coverage reports,
analysis outputs and local audit notes are excluded from version control.
