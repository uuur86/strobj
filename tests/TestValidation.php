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
use StrObj\Data\DataObject;
use StrObj\Data\Validation;

class TestValidation extends TestCase
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

    public function testValidation()
    {
        $data = json_decode($this->getTestData());
        $data = new DataObject($data);
        $validation = new Validation($data, [
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
        ]);

        $this->assertFalse($validation->isValid('persons/0/age'));
        $this->assertTrue($validation->isValid('persons/1/age'));
        $this->assertFalse($validation->isValid('persons/*/age'));
        $this->assertFalse($validation->isValid('persons'));
    }
}
