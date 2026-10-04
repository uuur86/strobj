# Error Handling

StrObj reports problems with standard SPL exceptions. Every one of them extends
`Exception`, so a `catch (Exception $e)` block handles all of them.

| Exception | When | Typical response |
| --- | --- | --- |
| `InvalidArgumentException` code 22 | JSON input cannot be decoded | Client error (HTTP 400/422) |
| `InvalidArgumentException` code 23 | JSON root is a scalar (`"text"`, `5`) | Client error |
| `InvalidArgumentException` code 24 | Input is not an array, object or JSON string | Programming error |
| `InvalidArgumentException` | Invalid options, an empty or wildcard write path, a path above 512 segments, a write the target object rejects, a cast that cannot be performed (default behavior) | Fix the configuration or reject the path |
| `UnexpectedValueException` | A validation pattern cannot be used | Fix the pattern |
| `JsonException` | `toJson()` cannot encode the data (NAN, INF, invalid UTF-8, recursion), or a `json` filter receives invalid JSON (default behavior) | Clean the data |
| `OverflowException` | The optional `memory_limit` guard is exceeded | Raise or remove the limit |

## Recommended pattern

Translate library exceptions into your own domain exception at the boundary
where you parse untrusted input, and do not show exception messages to end users
(CWE-209):

```php
use InvalidArgumentException;
use StrObj\StringObjects;

final class InvalidPayload extends RuntimeException
{
}

function parsePayload(string $body): StringObjects
{
    try {
        return StringObjects::instance($body, PayloadRules::OPTIONS);
    } catch (InvalidArgumentException $exception) {
        throw new InvalidPayload('The request body is not a valid JSON document.', 0, $exception);
    }
}
```

Log the original exception (`getPrevious()`) for diagnostics.
