<?php

namespace StrObj;

use \PHPUnit\Framework\TestCase;

class TestScenarios extends TestCase
{
    /**
     * Test Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function samplePersonsData()
    {
        return '{
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
    }



    public function testSetNewValue()
    {
        $persons = $this->samplePersonsData();

        // converts json string to object if input is string
        $test = StringObjects::instance(
            $persons,
            [
                'validation' => [
                    'patterns' => [
                        'age' => '#^[0-9]+$#siu',
                        'name' => '#^[a-zA-Z ]+$#siu', // only letters and spaces
                    ],
                    'rules' => [
                        [
                            'path' => 'persons/*/age',
                            'pattern' => 'age',
                            'required' => true
                        ],
                        [
                            'path' => 'persons/*/name',
                            'pattern' => 'name',
                            'required' => true
                        ],
                    ],
                ],
                'middleware' => [
                    'memory_limit' => 1024 * 1024 * 3, // 3MB
                ],
                'filters' => [
                    'persons/*/age' => [
                        'type' => 'int',
                        'callback' => function ($value) {
                            return $value > 10;
                        }
                    ],
                    'persons/*/name' => [
                        'type' => 'string',
                        'callback' => function ($value) {
                            return preg_match('#^[a-zA-Z ]+$#siu', $value);
                        }
                    ],
                ],
                // TODO: add below features
                'debug' => true,
                'log' => [
                    'path' => __DIR__ . '/logs',
                    'file' => 'test.log',
                    'level' => 'debug',
                ],
                'cache' => [
                    'path' => __DIR__ . '/cache',
                    'file' => 'test.cache',
                    'ttl' => 3600,
                ],
                'output' => [
                    'format' => 'array',
                    'structure' => [
                        'data' => [
                            'names' => 'persons/*/name',
                        ],
                    ]
                ],
            ]
        );

        $this->assertFalse($test->isValid('persons/0/age'));
        $this->assertTrue($test->isValid('persons/1/age'));
        $this->assertFalse($test->isValid('persons/*/age'));
        $this->assertFalse($test->isValid('persons'));

        // sets value to persons/0/name
        $test->set('persons/0/name', 'John D.');

        // sets value to persons/0/age
        $test->set('persons/0/age', 12);

        // sets value to persons/4/name
        $test->set('persons/4/name', 'Neo Doe');

        // sets value to persons/4/age
        $test->set('persons/4/age', 199);

        // outputs all persons before set
        // var_dump($test->get('persons'));

        $this->assertSame(
            'John D.',
            $test->get('persons/0/name')
        );
        $this->assertSame(
            '21',
            $test->get('persons/3/age')
        );
        $this->assertSame(
            'Neo Doe',
            $test->get('persons/4/name')
        );
        $this->assertSame(
            199,
            $test->get('persons/4/age')
        );

        // outputs all persons after set
        var_dump($test->get('persons/*/age'));
        var_dump($test->get('persons/*/name'));

        // sets value to persons/4/age
        $test->set('persons/4/age', 200);

        $this->assertSame(
            200,
            $test->get('persons/4/age')
        );

        // outputs all persons after second set
        var_dump($test->get('persons/4/age'));
        var_dump($test->get('persons/*/age'));

        // outputs all persons as array
    }


    // public function testThatValid()
    // {
    //     $obj = $this->sampleData();

    //     $data = StringObjects::instance($obj);

    //     $data->addRegexType('text_regex', '#^[a-z0-9 ]+$#siu');

    //     $data->validator(
    //         'a/*/b', // path
    //         'text_regex', // regex type
    //         true // required?
    //     );

    // $this->assertTrue($data->isValid('a/1'));
    // $this->assertTrue($data->isValid('a/1/b'));

    // // This array contains non valid value
    // $this->assertFalse($data->isValid('*'));
    // $this->assertFalse($data->isValid('a'));
    // $this->assertFalse($data->isValid('a/*'));
    // $this->assertFalse($data->isValid('a/*/b'));
    // $this->assertFalse($data->isValid('a/0'));
    // $this->assertFalse($data->isValid('a/0/b'));

    // // non exists values
    // $this->assertFalse($data->isValid(null));
    // $this->assertFalse($data->isValid(''));
    // $this->assertFalse($data->isValid('b'));
    // $this->assertFalse($data->isValid('b/*'));
    // $this->assertFalse($data->isValid('b/2'));
    // $this->assertFalse($data->isValid('z/*'));
    // $this->assertFalse($data->isValid('z/2'));
    // $this->assertFalse($data->isValid('b/2/*'));
    // $this->assertFalse($data->isValid('a/*/c'));

    // // exists path
    // $this->assertTrue($data->isPathExists('a'));
    // $this->assertTrue($data->isPathExists('a/*/b'));
    // $this->assertTrue($data->isPathExists('a/0/b'));
    // $this->assertTrue($data->isPathExists('a/1/b'));
    // $this->assertTrue($data->isPathExists('a/6'));

    // // non exists path
    // $this->assertFalse($data->isPathExists('z/*'));
    // $this->assertFalse($data->isPathExists('z/2'));

    // // null is exists? of course no!
    // $this->assertFalse($data->isPathExists(''));

    // // It has everything? absolutely not!
    // $this->assertFalse($data->isPathExists('*'));
    // }


    // public function testThatSameResults()
    // {
    //     $obj = $this->sampleData();

    //     $data = StringObjects::instance($obj);

    //     $this->assertSame(
    //         $obj->a,
    //         $data->get('a')
    //     );
    //     $this->assertSame(
    //         $obj->a[0],
    //         $data->get('a/0')
    //     );
    //     $this->assertSame(
    //         $obj->a[1],
    //         $data->get('a/1')
    //     );
    //     $this->assertSame(
    //         true,
    //         $data->get('a/2')
    //     );
    //     $this->assertSame(
    //         false,
    //         $data->get('a/3')
    //     );
    //     $this->assertSame(
    //         '',
    //         $data->get('a/4')
    //     );
    //     $this->assertSame(
    //         null,
    //         $data->get('a/5')
    //     );
    //     $this->assertSame(
    //         0,
    //         $data->get('a/6')
    //     );
    //     $this->assertSame(
    //         1,
    //         $data->get('a/7')
    //     );
    //     $this->assertSame(
    //         [
    //             "a/0" => $data->get("a/0"),
    //             "a/1" => $data->get("a/1"),
    //             "a/2" => true,
    //             "a/3" => false,
    //             "a/4" => '',
    //             "a/5" => null,
    //             "a/6" => 0,
    //             "a/7" => 1,
    //         ],
    //         $data->get('a/*')
    //     );
    //     $this->assertSame('I`m here!', $data->get('a/0/b'));
    //     $this->assertSame('I am here', $data->get('a/1/b'));
    //     $this->assertSame(
    //         [
    //             'a/0/b' => 'I`m here!',
    //             'a/1/b' => 'I am here',
    //         ],
    //         $data->get('a/*/b')
    //     );
    // }
}
