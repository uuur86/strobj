# PHP formatting

Open the repository folder in VS Code or Cursor, enable the existing
[PHP Sniffer & Beautifier extension](https://github.com/valeryan/vscode-phpsab)
(`valeryanm.vscode-phpsab`), and run `composer install` once.
The workspace settings select this extension and the project's `phpcs.xml`.
They keep formatting on save and paste disabled, and word-based suggestions off.

For a PHP file, press **Shift+Alt+F** on Windows or choose **Format Document**.
The command palette also provides **PHPCBF: Fix this file**.
Save the formatted document normally afterwards.

## Shared rules

The formatter uses the Composer-installed PHPCS/PHPCBF 3.x tools. The editor
and terminal read the same rules. No separate global coding standard is needed.

- Use PSR-12, four spaces per scope, UTF-8 and LF line endings. Pure PHP files
  require exact scope indentation; mixed HTML templates retain attribute alignment.
- Add a blank line before a control statement or `return` when other statements
  precede it, and after a completed control block before subsequent code.
- Keep `elseif`/`else`, `catch`/`finally` and `do`/`while` continuations together.
- Keep explanatory comments attached to their code and trailing comments on
  their original line. Align declaration docblock stars while preserving
  documentation text and literal contents.

The extra spacing rule reproduces the blank lines added;
PSR-12 alone does not require all of these gaps.
Existing underscore-prefixed properties are retained through one naming-rule
exception; formatting does not rename API or storage members.
For example:

```php
$value = $form->get($path, $default);

if (is_bool($value)) {
    return $value ? '1' : '0';
}

return is_scalar($value) ? (string) $value : '';
```

`.editorconfig` provides the same indentation and line-ending defaults to other
editors. The workspace recommends the EditorConfig extension as well.

## Terminal commands

Format and check the PHP files you change:

```bash
composer format -- examples/02-crud/web.php
composer format:check -- examples/02-crud/web.php
```

Paths may contain spaces. Without path arguments, these commands scan `src/`,
`tests/`, `tools/` and `examples/`. Formatting is applied only when explicitly
requested; enabling these rules does not reformat the existing repository.
PHPCBF fixes supported violations, while PHPCS also reports issues requiring
manual edits. The `format` script normalizes PHPCBF's successful-fix exit code.

Regression tests cover the chosen spacing, control-flow continuations, preserved
comments/docblocks/literals, valid PHP syntax and an unchanged second pass:

```bash
composer test:integration -- --filter FormattingTest
```

If formatting does not start, check the **PHP Sniffer & Beautifier** output
channel, confirm development dependencies are installed, and ensure `php` is
on the editor's PATH. The executable paths are relative to this workspace;
personal PHP installation paths belong in user settings.
