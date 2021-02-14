# strobj
PHP String to Objects Referrer.

```bash
composer require uuur86/strobj
```

```php
use StrObj\StringObjects;

require('vendor/autoload.php');

$testObj = (object) array('a' => (object) array( 'b' => 'I`m here!'));
$test = new StringObjects($testObj);

$test->check('a/b', '', '#[a-z0-9 ]+#siu');

if ($test->isValid('a/b')) {
  var_dump($test->get('a/b'));
} else {
  echo "a/b value is not acceptable!";
}

// prints "a/b value is not acceptable!"
```
