# Changelog

All notable changes to this project are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [2.2.0] - Unreleased

### Added

- `StringObjects::consistent()` and the `behavior` option select an explicit
  consistent profile: defaults apply only to missing fields, values are
  detached from inputs, and configuration is checked strictly. Existing
  applications keep the legacy profile through `StringObjects::instance()`.
  See [docs/compatibility.md](docs/compatibility.md).
- `DataObject::snapshot()`, `DataObject::setOffset()`, `DataObject::queryWithTransform()`,
  `DataCache::clearAll()`, `DataFilters::filterAt()` and `consistent()` factories
  for `Middleware`, `Validation` and `DataFilters`.
- Unit, regression and integration test suites with a 100% line coverage gate,
  PHPStan analysis and a shared coding standard.
- Runnable examples: a JSON table and a product CRUD application.

### Changed

- `instance()` throws `InvalidArgumentException` (a subclass of `Exception`)
  for invalid input and keeps the error codes 22, 23 and 24.
- `toJson()` throws `JsonException` when the data cannot be encoded, instead
  of failing with a `TypeError`.
- Reads always resolve the current data. The read cache was removed, so reads
  no longer return stale values or retain memory for every queried path.
  `DataObject::cache()` and `DataCache` remain available.

### Fixed

- Deep writes keep the whole document and no longer promote child keys to the root.
- `has()` reports existing `null` and `false` fields reliably.
- Validation refreshes after library writes and inherited SPL mutations.
- Numeric root keys resolve after the legacy profile casts a root array to an object.
- Reads take time proportional to the path depth instead of the root size.
- Legacy `toArray()` keeps nested values unchanged, as in v2.1.
- Filter casts never raise PHP errors or warnings for values they cannot convert.
- Values that break a validation pattern at runtime (malformed UTF-8, PCRE
  limits) fail validation instead of throwing.

### Security

- Untrusted values can no longer make validation throw.
- Vulnerabilities are reported privately; see [SECURITY.md](SECURITY.md).

## [2.1.9] - 2024-08-26

- Added a function to find inclusive paths.

[2.2.0]: https://github.com/uuur86/strobj/compare/v2.1.9...v2.2-dev
[2.1.9]: https://github.com/uuur86/strobj/releases/tag/v2.1.9
