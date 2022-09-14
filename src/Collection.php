<?php
/**
 * @package strobj
 */

namespace StrObj;

use RecursiveArrayIterator;
use Traversable;

class Collection extends RecursiveArrayIterator implements Traversable
{
  /**
   * @inheritdoc
   */
    public function __construct($data)
    {
        if (empty($data) || !in_array(gettype($data), ['array', 'object'])) {
            return;
        }

        parent::__construct($data);
    }



    public static function instance($data)
    {
        if ($data instanceof Collection) {
            return $data;
        }

        return new static($data);
    }
}
