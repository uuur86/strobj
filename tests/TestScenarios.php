<?php

include 'vendor/autoload.php';

use StrObj\StringObjects;
use PHPUnit\Framework\TestCase;

class TestScenarios extends TestCase
{
	public function sampleData()
	{
		return (object) array(
			'a' => array(
				(object) array('b' => 'I`m here!'),
				array('b' => 'I am here'),
				true,
				false,
				'',
				null,
				0,
				1,
			),
			'w' => null
		);
	}



	public function testThatValid()
	{
		$obj = $this->sampleData();

		$data = StringObjects::instance($obj);

		$data->addRegexType('text_regex', '#^[a-z0-9 ]+$#siu');

		$data->validator(
			'a/*/b',// path
			'text_regex',// regex type
			true // required?
		);

		$this->assertTrue($data->isValid('a/1'));
		$this->assertTrue($data->isValid('a/1/b'));

		// This array contains non valid value
		$this->assertFalse($data->isValid('*'));
		$this->assertFalse($data->isValid('a'));
		$this->assertFalse($data->isValid('a/*'));
		$this->assertFalse($data->isValid('a/*/b'));
		$this->assertFalse($data->isValid('a/0'));
		$this->assertFalse($data->isValid('a/0/b'));

		// non exists values
		$this->assertFalse($data->isValid(null));
		$this->assertFalse($data->isValid(''));
		$this->assertFalse($data->isValid('b'));
		$this->assertFalse($data->isValid('b/*'));
		$this->assertFalse($data->isValid('b/2'));
		$this->assertFalse($data->isValid('z/*'));
		$this->assertFalse($data->isValid('z/2'));
		$this->assertFalse($data->isValid('b/2/*'));
		$this->assertFalse($data->isValid('a/*/c'));

		// exists path
		$this->assertTrue($data->isPathExists('a'));
		$this->assertTrue($data->isPathExists('a/*/b'));
		$this->assertTrue($data->isPathExists('a/0/b'));
		$this->assertTrue($data->isPathExists('a/1/b'));
		$this->assertTrue($data->isPathExists('a/6'));

		// non exists path
		$this->assertFalse($data->isPathExists('z/*'));
		$this->assertFalse($data->isPathExists('z/2'));

		// null is exists? of course no!
		$this->assertFalse($data->isPathExists(''));

		// exception: a cannot contain everything
		$this->assertFalse($data->isPathExists('a/*'));

		// It has everything? absolutely not!
		$this->assertFalse($data->isPathExists('*'));
	}



	public function testThatSameResults()
	{
		$obj = $this->sampleData();

		$data = StringObjects::instance($obj);

		$this->assertSame(
			$obj->a,
			$data->get('a')
		);
		$this->assertSame(
			$obj->a[0],
			$data->get('a/0')
		);
		$this->assertSame(
			$obj->a[1],
			$data->get('a/1')
		);
		$this->assertSame(
			true,
			$data->get('a/2')
		);
		$this->assertSame(
			false,
			$data->get('a/3')
		);
		$this->assertSame(
			'',
			$data->get('a/4')
		);
		$this->assertSame(
			null,
			$data->get('a/5')
		);
		$this->assertSame(
			0,
			$data->get('a/6')
		);
		$this->assertSame(
			1,
			$data->get('a/7')
		);
		$this->assertSame(
			[
				"a/0" => $data->get("a/0"),
				"a/1" => $data->get("a/1"),
				"a/2" => true,
				"a/3" => false,
				"a/4" => '',
				"a/5" => null,
				"a/6" => 0,
				"a/7" => 1,
			],
			$data->get('a/*')
		);
		$this->assertSame('I`m here!', $data->get('a/0/b'));
		$this->assertSame('I am here', $data->get('a/1/b'));
		$this->assertSame(
			[
				'a/0/b' => 'I`m here!',
    		'a/1/b' => 'I am here'
			],
			$data->get('a/*/b')
		);
	}
}
?>