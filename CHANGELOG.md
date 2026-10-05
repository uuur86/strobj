# Changelog

All notable changes to this project are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [Unreleased][]

### Fixed

- Consistent instances no longer raise PHP 8.5 deprecation notices. Snapshots
  store the public entries of an object root in an array instead of using the
  object as `ArrayIterator` storage, which PHP 8.5 deprecates.
- Snapshots of class instances no longer expose protected and private properties
  under NUL-prefixed keys in `toArray()` and root reads.
- Appending to a snapshot whose object root has numeric property names no longer
  replaces an existing entry on PHP 8.1 and later.
- The CRUD example sets the `Secure` flag on its session cookie for HTTPS requests.
- The Codacy workflow uploads its SARIF results again. Code scanning rejects
  several runs of one tool in the same category, so each run gets its own
  category.

### Changed

- Internal refactoring for SonarQube Cloud maintainability rules: lower cognitive
  complexity, no nested ternary operators and fewer exit points. Private properties
  of `StringObjects` and `Middleware` lost their underscore prefix; serialized
  instances from earlier versions must be recreated.
- GitHub Actions are pinned to commit SHAs, checkouts no longer persist
  credentials, and the wiki workflow requests write access only for its job.
- Tests no longer use `eval()`: contract and PHP 8.1/8.4 syntax fixtures are
  regular classes in `tests/Fixtures`.

### Added

- `.sonarcloud.properties` separates tests from sources in automatic analysis.
- Documentation on caching documents with Memcached, Redis or PSR-16 caches
  ([#6][]).

### Known issues

- The legacy behavior and `new DataObject($object)` keep the caller's object as
  SPL storage, as in v2.1, and raise `E_DEPRECATED` on PHP 8.5. See
  [docs/compatibility.md](docs/compatibility.md#object-roots-and-php-85).

## [3.0.0][] - 2026-10-05

### Breaking changes

- `StringObjects::instance()` uses the consistent behavior by default: stored
  values (including `false` and `null`) are returned unchanged, defaults apply only
  to missing fields and rejected values, wildcard reads keep one entry per element,
  values are detached from the input, and configuration is checked strictly.
  Keep the 2.x results with `['behavior' => Behavior::LEGACY]`.
  See [docs/compatibility.md](docs/compatibility.md) for every difference.
- `StringObjects` no longer exposes the internal `castType()`, `convertToByte()`
  and `convertToString()` helpers. They remain on the components that use them
  and are marked `@internal`.
- The license changes from GPL-2.0-or-later to MIT. Releases up to 2.1.9 remain
  available under GPL-2.0-or-later.

### Added

- `StrObj\Behavior` with the `CONSISTENT` (default) and `LEGACY` behaviors,
  selected with the `behavior` option.
- `StringObjects::ERROR_INVALID_JSON`, `ERROR_SCALAR_JSON` and
  `ERROR_UNSUPPORTED_INPUT` name the exception codes 22, 23 and 24.
- `DataObject::snapshot()`, `DataObject::setOffset()`, `DataObject::queryWithTransform()`,
  `DataCache::clearAll()`, `DataFilters::filterAt()` and `consistent()` factories
  for `Middleware`, `Validation` and `DataFilters`.
- Unit, regression and integration test suites with a 100% line coverage gate,
  PHPStan analysis and a shared coding standard.
- Runnable examples: a JSON table and a product CRUD application.

### Changed

- The `mbstring` extension is no longer required. Distribution archives contain
  only the library, its license and its documentation.
- `instance()` throws `InvalidArgumentException` (a subclass of `Exception`)
  for invalid input and keeps the error codes 22, 23 and 24.
- `toJson()` throws `JsonException` when the data cannot be encoded, instead
  of failing with a `TypeError`.
- Path writes that an object rejects (NUL-prefixed names, inaccessible, readonly
  or incompatible typed properties) throw `InvalidArgumentException` instead of
  a PHP `Error`.
- Reads always resolve the current data. The read cache was removed, so reads
  no longer return stale values or retain memory for every queried path.
  `DataObject::cache()` and `DataCache` remain available.

### Deprecated

- `DataCache` and `DataObject::cache()`: reads no longer consult the cache.

### Fixed

- Deep writes keep the whole document and no longer promote child keys to the root.
- `has()` reports existing `null` and `false` fields reliably.
- Validation refreshes after library writes and inherited SPL mutations.
- Numeric root keys resolve after the legacy behavior casts a root array to an object.
- Reads take time proportional to the path depth instead of the root size.
- Legacy `toArray()` keeps nested values unchanged, as in v2.1.
- Legacy wildcard reads (`prefix/*/field`) follow the v2.1 `array_column()` contract.
- In the consistent behavior, a value rejected by a filter callback returns the
  supplied default, so it can no longer be confused with a stored `false`.
- PHP 7.4 and 8.0 are supported again: snapshots of objects with private state,
  numeric root keys and padded numeric byte limits work on every runtime.
- Filter casts never raise PHP errors or warnings for values they cannot convert.
- Values that break a validation pattern at runtime (malformed UTF-8, PCRE
  limits) fail validation instead of throwing.

### Security

- Untrusted values can no longer make validation throw.
- Vulnerabilities are reported privately; see [SECURITY.md](SECURITY.md).

## [2.1.9][] - 2024-08-26

- Added a function to find inclusive paths.

[Unreleased]: https://github.com/uuur86/strobj/compare/v3.0.0...HEAD
[3.0.0]: https://github.com/uuur86/strobj/compare/v2.1.9...v3.0.0
[2.1.9]: https://github.com/uuur86/strobj/releases/tag/v2.1.9
[#6]: https://github.com/uuur86/strobj/issues/6
