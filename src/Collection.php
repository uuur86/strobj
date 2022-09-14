<?php
/**
 * @package strobj
 */

namespace StrObj;

use RecursiveArrayIterator;
use Iterator;
use JsonSerializable;

class Collection extends RecursiveArrayIterator implements Iterator, JsonSerializable
{
    /**
     * @inheritdoc
     */
    public function __construct($data)
    {
        if (empty($data) || !(is_array($data) || is_object($data)) || is_callable($data)) {
            return;
        }

        $this->data = $data;

        parent::__construct($data);
    }

    /**
     * @inheritdoc
     */
    public static function instance($data)
    {
        if ($data instanceof Collection) {
            return $data;
        }

        return new static($data);
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return $this->data;
    }
}
