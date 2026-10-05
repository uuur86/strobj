---
name: Bug report
about: Report incorrect results, errors or crashes
title: ''
labels: bug
assignees: ''
---

**Description**
What happens, in one or two sentences.

**Minimal reproduction**

```php
use StrObj\StringObjects;

$data = StringObjects::instance(/* input */, [/* options, including 'behavior' if set */]);
var_dump($data->get('path'));
```

**Expected result**

**Actual result**
Include the full exception message and stack trace, with secrets and personal data removed.

**Environment**
- StrObj version:
- PHP version:
- Framework (if any):

**Additional context**
