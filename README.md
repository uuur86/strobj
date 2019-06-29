# strobj
PHP String to Objects Referrer.

```php
$testObj = (object) array( 'a' => (object) array( 'b' => 'I`m here!' ) );
$test = new StringObjects( $testObj );

var_dump( $test->get( 'a/b' ) );

// prints string(9) "I`m here!"
```
