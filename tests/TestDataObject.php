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

class TestDataObject extends TestCase
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

    public function testDataObjectGetValues()
    {
        $data     = json_decode($this->getTestData());
        $data_obj = new DataObject($data);

        $this->assertSame(
            'John Doe',
            $data_obj->get('persons/0/name')
        );
        $this->assertSame(
            '21',
            $data_obj->get('persons/3/age')
        );

        $data_obj->set('persons/4/name', 'Neo Doe');
        $data_obj->set('persons/4/age', 199);

        $this->assertSame(
            'Neo Doe',
            $data_obj->get('persons/4/name')
        );
        $this->assertSame(
            199,
            $data_obj->get('persons/4/age')
        );
    }
}
