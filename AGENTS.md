# Repository conventions

- Use English for code, comments, docblocks, example interfaces and fixtures,
  error messages, documentation, and public reports.
- Preserve existing docblocks; correct inaccurate descriptions instead of
  removing documentation. Preserve copyright holder names as written.
- Write PHP to the project `phpcs.xml` rules: PSR-12, four spaces, LF line endings,
  blank lines before control statements and returns, and after control blocks.
  Keep comments attached to their code. Use `composer format -- <changed paths>`
  and `composer format:check -- <changed paths>` before finishing PHP changes.
  Preserve all docblocks and behavior during formatting. VS Code / Cursor uses
  `valeryanm.vscode-phpsab`; keep formatting on save and paste disabled.
- Public reports belong in `docs/reports/` and must be written in English.
- Never stage changes, create commits, push, publish, deploy, or release unless
  the user explicitly requests that exact action in the current task.
