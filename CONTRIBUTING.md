# Contributing to StrObj

Thank you for helping improve StrObj. This guide explains how to propose changes
so that they can be reviewed and released safely.

## Code of conduct

Everyone taking part in this project is expected to follow the
[Code of Conduct](CODE_OF_CONDUCT.md).

## Reporting bugs and requesting features

- Search the [existing issues](https://github.com/uuur86/strobj/issues) first.
- Use the issue templates. A bug report needs the StrObj and PHP versions, the
  `behavior` option in use, a minimal code sample, and the expected and actual
  results.
- Report security vulnerabilities privately, as described in
  [SECURITY.md](SECURITY.md). Never open a public issue for them.

## Development setup

Requirements: PHP 7.4 or newer, Composer, and PCOV or Xdebug for coverage.

```bash
git clone https://github.com/uuur86/strobj.git
cd strobj
composer install
```

## Making a change

1. Create a branch from the current development branch (`v2.2-dev` for 3.0).
2. Write the change and a test that fails without it. Regression tests go in
   `tests/Regression/` and reference the issue they cover.
3. Keep public method signatures compatible unless the change targets a major
   release, and describe user-visible changes in [CHANGELOG.md](CHANGELOG.md).
4. Write code, comments, documentation and messages in English.

## Checks

Every pull request must pass the same checks as CI:

```bash
composer test                                # unit, regression and integration tests
composer test:coverage                       # 100% line coverage of src/
composer phpstan                             # static analysis, PHP 7.4 target
composer format:check -- <changed paths>     # PSR-12 and project spacing rules
```

Format changed files with `composer format -- <changed paths>`. See
[docs/testing.md](docs/testing.md) and [docs/formatting.md](docs/formatting.md)
for details.

## Pull requests

- Keep each pull request focused on one change and link the related issue.
- Explain what changes for users and how you tested it.
- Update the README, the wiki source in [docs/wiki](docs/wiki) and the changelog
  when behavior or public APIs change.

By contributing, you agree that your contributions are licensed under the
[MIT License](LICENSE).
