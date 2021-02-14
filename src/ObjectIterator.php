<?php
namespace StrObj;

class ObjectIterator implements \Iterator
{
	private $position = 0;


	function rewind()
	{
		$this->position = 0;
	}



	function current()
	{
		return $this->current;
	}



	function key()
	{
		return $this->position;
	}



	function next()
	{
		++$this->position;
	}



	function valid()
	{
		return true;
	}
}
