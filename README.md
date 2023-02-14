
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

## GETTING STARTED

### BASIC USAGE

```php
use StrObj\StringObjects;

require('vendor/autoload.php');

// String JSON data to be used
// or you can use an object/array
$persons = '{
    "persons": [
        {
            "name": "John Doe",
            "age": "twelve"
        },
        {
            "name": "Molly Doe",
            "age": "14"
        },
        {
            "name": "Lorem Doe",
            "age": "34"
        },
        {
            "name": "Ipsum Doe",
            "age": "21"
        }
    ]
}';

$test = StringObjects::instance(
    $persons,
    [
        'validation' => [
            'patterns' => [
                // Add a new pattern named 'age' which only accepts numbers
                'age' => '#^[0-9]+$#siu',
                // Add a new pattern named 'name' which only accepts letters and spaces
                'name' => '#^[a-zA-Z ]+$#siu',
            ],
            'rules' => [
                // first rule
                [
                    // path scope to be checked
                    'path' => 'persons/*/age',
                    // uses 'age' pattern
                    'pattern' => 'age',
                    // makes it required
                    'required' => true
                ],
                // second rule
                [
                    'path' => 'persons/*/name',
                    'pattern' => 'name',
                    'required' => true
                ],
            ],
        ],
        'middleware' => [
            // Sets memory limit to 3MB
            'memory_limit' => 1024 * 1024 * 3,
        ],
        // Output data filters
        'filters' => [
            // Filters all persons/*/age values
            'persons/*/age' => [
                // converts to integer
                'type' => 'int',
                // only accepts values greater than 10
                'callback' => function ($value) {
                    return $value > 10;
                }
            ],
            'persons/*/name' => [
                // converts to string (not necessary)
                'type' => 'string',
                // only accepts values which contains only letters and spaces
                'callback' => function ($value) {
                    return preg_match('#^[a-zA-Z ]+$#siu', $value);
                }
            ],
        ],
    ]
);

// False
var_dump($test->isValid('persons/0/age'));

// True
var_dump($test->isValid('persons/1/age'));

// False
var_dump($test->isValid('persons/*/age'));

// False
var_dump($test->isValid('persons'));

// Updates value of persons/0/name
$test->set('persons/0/name', 'John D.');

// Updates value of persons/0/age
$test->set('persons/0/age', 12);

// Adds a new person named "Neo Doe" with age 199
$test->set('persons/4/name', 'Neo Doe');
$test->set('persons/4/age', 199);

// Outputs "John D."
$test->get('persons/0/name');

// Outputs "12"
$test->get('persons/3/age');

// Outputs "Neo Doe"
$test->get('persons/4/name');

// Outputs "199"
$test->get('persons/4/age');

// Updates value of persons/4/age to "200"
$test->set('persons/4/age', 200);

// Outputs "200"
$test->get('persons/4/age');
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
