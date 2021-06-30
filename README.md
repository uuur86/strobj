# strobj
## PHP String Objects
This repository allows you to access objects via string. You can also check if their values are valid.

```bash
composer require uuur86/strobj
```

BASIC USAGE
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
```

DATA Validation

```php
use StrObj\StringObjects;

require('vendor/autoload.php');

$persons = (object)array(
	"persons" => array(
		(object)array(
			"name" => "John-Doe",
			"age" => "twelve"
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

// defines regex types which is going to use in control method
$test->addRegexType('age', '#^[0-9]{1,3}$#siu');

$test->validator('persons/*/age', 'age', true);
$test->validator('persons/*/name', '', true, '#^[a-z0-9 ]+$#siu');

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
