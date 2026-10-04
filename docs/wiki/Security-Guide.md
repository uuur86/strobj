# Security Guide

StrObj is designed to process untrusted documents. This page lists what the
library guarantees and what remains the application's responsibility.

## What StrObj guarantees

| Risk | Behavior |
| --- | --- |
| Malformed or deeply nested JSON (CWE-20, CWE-674) | Decoding stops at depth 512 and throws `InvalidArgumentException` (code 22). |
| Very long paths (CWE-400) | Paths are limited to 512 segments. |
| Cyclic or very deep objects (CWE-674) | Consistent snapshots reject cycles and depths above 512 with `InvalidArgumentException`. |
| Validation crashes caused by values (CWE-1333, CWE-248) | Malformed UTF-8 and values that exceed PCRE limits fail validation instead of throwing. |
| Path writes rejected by objects (CWE-248) | NUL-prefixed names, inaccessible, readonly or incompatible typed properties throw `InvalidArgumentException`, never a PHP `Error`. |
| Private state disclosure (CWE-200) | Path reads only see public properties; `toJson()` and the consistent `toArray()` follow PHP's JSON serialization rules. |
| Code execution | The library never evaluates, includes or unserializes data. |
| Ambiguous values (CWE-843) | In the consistent profile, stored `false`/`null` are never replaced, and the default marks missing or rejected values. |

## Your responsibilities

### Treat options as code

Validation patterns, filter types and callbacks are configuration. In the
consistent profile, a callback may be any PHP callable, including a function name
string. **Never build options from request data, database rows editable by users,
or uploaded files.** A user-controlled callback is remote code execution
(CWE-94, CWE-470); a user-controlled pattern is a ReDoS vector (CWE-1333).

### Writing with user-supplied paths

`set()` writes wherever the path points. When a path or field name comes from a
request, use a whitelist (CWE-915, mass assignment):

```php
$writable = ['profile/name', 'profile/city', 'settings/newsletter'];

foreach ($writable as $path) {
    if ($patch->has($path)) {
        $document->set($path, $patch->get($path));
    }
}
```

Do not pass request keys directly to `set()`, and do not wrap objects whose public
properties or `__set()` methods have side effects.

### Limit input size

StrObj does not limit document size. Enforce limits before decoding: web server
body limits, `post_max_size`, or a bounded read as shown in
[Custom PHP Integration](Custom-PHP-Integration). The optional
`middleware.memory_limit` guard is a last line of defense, not an input limit.

### Write safe patterns

Anchor every pattern, avoid nested quantifiers, and use the `u` modifier for
UTF-8 text. See [Validation](Validation#writing-safe-patterns).

### Encode output

Values are returned as stored. Escape them for their destination: HTML
(`htmlspecialchars`, CWE-79), SQL (prepared statements, CWE-89), shell
(`escapeshellarg`, CWE-78) and logs (strip control characters, CWE-117).

### Hide internal errors

Catch library exceptions at the boundary, log them, and return a generic message
to clients (CWE-209). See [Error Handling](Error-Handling).

## Reporting a vulnerability

Report vulnerabilities privately, as described in the repository's
[security policy](https://github.com/uuur86/strobj/security/policy). Do not
open public issues for security problems.
