<?php
/**
 * StrObj: PHP String Object Project
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package strobj
 * @license GPLv2
 * @author Uğur Biçer <info@ugurbicer.com.tr>
 * @version 1.0.0
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
    if (empty($data) || ! in_array(gettype($data), ['array', 'object'])) {
      return false;
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