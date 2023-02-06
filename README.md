
# PHP String Objects

This repository allows you to access objects via string.
You can also check if their values are valid.

```bash
composer require uuur86/strobj
```

## TESTS

```bash
composer test
```

or

```bash
php vendor/bin/phpunit tests/TestScenarios
```

## BASIC USAGE

```php
use StrObj\StringObjects;

require('vendor/autoload.php');

$persons = (object)array(
  "persons" => array(
    (object)array(
      "name" => "John Doe",
      "age" => 12
    ),
    (object)array(
      "name" => "Molly Doe",
      "age" => 14
    ),
    (object)array(
      "name" => "Lorem Ipsum",
      "age" => 21
    )
  )
);

$test = StringObjects::instance($persons);

// prints all persons
var_dump($test->get('persons'));

// prints first person's name
var_dump($test->get('persons/0/name'));

// prints all person's name
var_dump($test->get('persons/*/name'));
// result:
```

### DATA Validation

```php
use StrObj\StringObjects;

require('vendor/autoload.php');

$persons = json_decode('{
    persons: [
        {
            name: "John Doe",
            age: "twelve"
        },
        {
            name: "Molly Doe",
            age: 14
        },
        {
            name: "Lorem Doe",
            age: 34
        },
        {
            name: "Ipsum Doe",
            age: 21
        }
    ]
}');

$test = StringObjects::instance($persons);

// defines regex types which is going to use in control method
$test->addRegexType('age', '#^[0-9]{1,3}$#siu');

$test->addValidator('persons/*/age', 'age', true);
$test->addValidator('persons/*/name', '', true, '#^[a-z0-9 ]+$#siu');

// prints "persons/*/name values are not acceptable!"
if ($test->isValid('persons/*/name')) {
  var_dump($test->get('persons/*/name'));
} else {
  echo "persons/*/name values are not acceptable!";
}

// prints "persons/0/age value is not acceptable!"
if ($test->isValid('persons/0/age')) {
  var_dump($test->get('persons/0/age'));
} else {
  echo "persons/0/age value is not acceptable!";
}

// prints "14"
if ($test->isValid('persons/1/age')) {
  var_dump($test->get('persons/1/age'));
} else {
  echo "persons/1/age value is not acceptable!";
}

// prints "persons/*/age values are not acceptable!"
if ($test->isValid('persons/*/age')) {
  var_dump($test->get('persons/*/age'));
} else {
  echo "persons/*/age values are not acceptable!";
}

```

### SET VALUES

```php
use StrObj\StringObjects;

require('vendor/autoload.php');

$persons = '{
    persons: [
        {
            name: "John Doe",
            age: "twelve"
        },
        {
            name: "Molly Doe",
            age: 14
        },
        {
            name: "Lorem Doe",
            age: 34
        },
        {
            name: "Ipsum Doe",
            age: 21
        }
    ]
}';

// converts json string to object if input is string
$test = StringObjects::instance($persons);

// sets value to persons/0/name
$test->set('persons/0/name', 'John Doe');

// sets value to persons/0/age
$test->set('persons/0/age', 12);

// sets value to persons/4/name
$test->set('persons/4/name', 'Neo Doe');

// sets value to persons/4/age
$test->set('persons/4/age', 1);

// outputs all persons
var_dump($test->get('persons'));
// results:
// array(5) {
//   [0]=>
//   object(stdClass)#2 (2) {
//     ["name"]=>
//     string(8) "John Doe"
//     ["age"]=>
//     int(12)
//   }
//   [1]=>
//   object(stdClass)#3 (2) {
//     ["name"]=>
//     string(8) "Molly Doe"
//     ["age"]=>
//     int(14)
//   }
//   [2]=>
//   object(stdClass)#4 (2) {
//     ["name"]=>
//     string(9) "Lorem Doe"
//     ["age"]=>
//     int(34)
//   }
//   [3]=>
//   object(stdClass)#5 (2) {
//     ["name"]=>
//     string(9) "Ipsum Doe"
//     ["age"]=>
//     int(21)
//   }
//   [4]=>
//   object(stdClass)#6 (2) {
//     ["name"]=>
//     string(8) "Neo Doe"
//     ["age"]=>
//     int(1)
//   }
// }
```

## LICENSE

GPL-2.0-or-later

## AUTHOR

Uğur Biçer - @uuur86

## CONTRIBUTING

If you want to contribute to this project, you can send pull requests.

## CONTACT

You can contact me via email: contact@codeplus.dev

## BUGS

You can report bugs via github issues.

## SECURITY

If you find a security issue, please report it via email: contact@codeplus.dev

## DONATE

If you want to support me, you can donate via github sponsors: <https://github.com/sponsors/uuur86>

## SEE ALSO

- [uuur86/wpoauth]( https://github.com/uuur86/wpoauth ) - Wordpress OAuth2 Client
- [@codeplusdev]( https://github.com/codeplusdev ) - Codeplus Development
