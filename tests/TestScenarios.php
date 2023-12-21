<?php

/**
 * This file is part of the StrObj package.
 *
 * (c) Uğur Biçer <contact@codeplus.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package  StrObj
 * @version  GIT: <git_id>
 * @link     https://github.com/uuur86/strobj
 */

namespace StrObj;

use PHPUnit\Framework\TestCase;

class TestScenarios extends TestCase
{
    /**
     * Test Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function samplePersonsObjectData()
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

    public function samplePersonsArrayData()
    {
        return '[
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
            ]';
    }

    public function samplePersonsPHPArrayData()
    {
        return json_decode('[
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
            ]', true);
    }



    public function testObjectValue()
    {
        $persons = $this->samplePersonsObjectData();

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

        // sets value to persons/4/age
        $test->set('persons/4/age', 200);

        $this->assertSame(
            200,
            $test->get('persons/4/age')
        );
    }

    public function testArrayValue()
    {
        $persons = $this->samplePersonsArrayData();

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
                            'path' => '*/age',
                            'pattern' => 'age',
                            'required' => true
                        ],
                        [
                            'path' => '*/name',
                            'pattern' => 'name',
                            'required' => true
                        ],
                    ],
                ],
                'middleware' => [
                    'memory_limit' => 1024 * 1024 * 3, // 3MB
                ],
                'filters' => [
                    '*/age' => [
                        'type' => 'int',
                        'callback' => function ($value) {
                            return $value > 10;
                        }
                    ],
                    '*/name' => [
                        'type' => 'string',
                        'callback' => function ($value) {
                            return preg_match('#^[a-zA-Z ]+$#siu', $value);
                        }
                    ],
                ],
            ]
        );

        $this->assertFalse($test->isValid('0/age'));
        $this->assertTrue($test->isValid('1/age'));
        $this->assertFalse($test->isValid('*/age'));
        $this->assertFalse($test->isValid(''));

        // sets value to 0/name
        $test->set('0/name', 'John D.');

        // sets value to 0/age
        $test->set('0/age', 12);

        // sets value to 4/name
        $test->set('4/name', 'Neo Doe');

        // sets value to 4/age
        $test->set('4/age', 199);

        $this->assertSame(
            'John D.',
            $test->get('0/name')
        );
        $this->assertSame(
            '21',
            $test->get('3/age')
        );
        $this->assertSame(
            'Neo Doe',
            $test->get('4/name')
        );
        $this->assertSame(
            199,
            $test->get('4/age')
        );

        // sets value to 4/age
        $test->set('4/age', 200);

        $this->assertSame(
            200,
            $test->get('4/age')
        );
    }

    public function testArrayPHPValue()
    {
        $persons = $this->samplePersonsPHPArrayData();

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
                            'path' => '*/age',
                            'pattern' => 'age',
                            'required' => true
                        ],
                        [
                            'path' => '*/name',
                            'pattern' => 'name',
                            'required' => true
                        ],
                    ],
                ],
                'middleware' => [
                    'memory_limit' => 1024 * 1024 * 3, // 3MB
                ],
                'filters' => [
                    '*/age' => [
                        'type' => 'int',
                        'callback' => function ($value) {
                            return $value > 10;
                        }
                    ],
                    '*/name' => [
                        'type' => 'string',
                        'callback' => function ($value) {
                            return preg_match('#^[a-zA-Z ]+$#siu', $value);
                        }
                    ],
                ],
            ]
        );

        $this->assertFalse($test->isValid('0/age'));
        $this->assertTrue($test->isValid('1/age'));
        $this->assertFalse($test->isValid('*/age'));
        $this->assertFalse($test->isValid(''));

        // sets value to 0/name
        $test->set('0/name', 'John D.');

        // sets value to 0/age
        $test->set('0/age', 12);

        // sets value to 4/name
        $test->set('4/name', 'Neo Doe');

        // sets value to 4/age
        $test->set('4/age', 199);

        $this->assertSame(
            'John D.',
            $test->get('0/name')
        );
        $this->assertSame(
            '21',
            $test->get('3/age')
        );
        $this->assertSame(
            'Neo Doe',
            $test->get('4/name')
        );
        $this->assertSame(
            199,
            $test->get('4/age')
        );

        // sets value to 4/age
        $test->set('4/age', 200);

        $this->assertSame(
            200,
            $test->get('4/age')
        );
    }
}
