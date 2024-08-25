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
use StrObj\Data\DataFilters;

class TestDataFilters extends TestCase
{
    /**
     * Test Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Fetches the raw JSON data as a string format
     *
     * @param boolean $isArray
     *
     * @return string
     */
    public function getTestData()
    {
        return file_get_contents('tests/test-data.json');
    }

    /**
     * Gets config data as a array format
     *
     * @return array
     */
    public function getFilters()
    {
        return [
            'persons/*/age' => [
                'type' => 'int',
                'callback' => function ($value) {
                    return ($value > 10);
                }
            ],
            'persons/*/name' => [
                'type' => 'string',
                'callback' => function ($value) {
                    return preg_match('#^[a-zA-Z ]+$#siu', $value);
                }
            ],
        ];
    }

    /**
     * Unit Test 1
     */
    public function testDataFilter()
    {
        $data    = json_decode($this->getTestData(), true);
        $filters = $this->getFilters();

        $filter_object   = new DataFilters($filters);
        $test_value_name = $filter_object->filter('persons/*/name', $data);
        $test_value_age  = $filter_object->filter('persons/*/age', $data);

        $this->assertSame(
            'John Doe',
            $test_value_name['persons'][0]['name']
        );

        $this->assertFalse($test_value_age['persons'][0]['age']);
        $this->assertSame($test_value_age['persons'][2]['age'], 34);

        print_r($test_value_name);
        print_r($test_value_age);
    }
}
