# Validation

Validation checks the **stored** data with regular expressions. It never changes
values and never throws because of a value.

## Configuration

```php
$user = StringObjects::instance($input, [
    'validation' => [
        'patterns' => [
            'email'  => '/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i',
            'digits' => '/^\d+$/',
        ],
        'rules' => [
            ['path' => 'user/email', 'pattern' => 'email',  'required' => true],
            ['path' => 'user/age',   'pattern' => 'digits', 'required' => true],
            ['path' => 'user/phone', 'pattern' => '/^\+?[0-9 ]{7,20}$/'],
        ],
    ],
]);

$user->isValid();              // every rule
$user->isValid('user/email');  // one field
$user->isValid('user');        // every rule below user/
```

- `patterns` maps names to regular expressions. A rule's `pattern` is either a
  pattern name or a regular expression.
- `required` defaults to `false`. In the default behavior it must be a boolean.
- Rule paths may contain wildcards: `items/*/qty` checks every item.

## How a value is checked

| Value | Required rule | Optional rule |
| --- | --- | --- |
| Missing, `null` or `''` | Invalid | Valid |
| String or number | Must match the pattern | Must match the pattern |
| `true` / `false` | Checked as `"1"` / `"0"` | Checked as `"1"` / `"0"` |
| Array or object | Invalid | Invalid |
| Malformed UTF-8 with a `/u` pattern, or a value that exceeds PCRE limits | Invalid | Invalid |

`isValid()` is `true` when no rule fails; with no rules it is always `true`.
Results refresh automatically after `set()` and other changes.

```php
$order = StringObjects::instance(['p' => [['age' => '1'], ['age' => 'x']]], ['validation' => [
    'rules' => [['path' => 'p/*/age', 'pattern' => '/^\d+$/', 'required' => true]],
]]);

$order->isValid('p/0/age');   // true
$order->isValid('p/1/age');   // false
$order->isValid();            // false
$order->set('p/1/age', '7');
$order->isValid();            // true
```

## Writing safe patterns

- Anchor patterns with `^` and `$` (or `\A` and `\z`); unanchored patterns match
  substrings.
- Avoid nested quantifiers such as `(a+)+`. They can cause catastrophic
  backtracking (ReDoS, CWE-1333). StrObj treats a value that exceeds PCRE's limits
  as invalid, but the attempt still costs CPU time.
- Add the `u` modifier for UTF-8 text.
- Patterns are configuration. Never accept them from users.

An unusable pattern (for example `/(/`) throws `UnexpectedValueException` the
first time it is evaluated, which usually happens when the instance is created.

Validation complements framework validation (such as Laravel's validator); it is
convenient for deep or wildcard paths inside JSON documents.
