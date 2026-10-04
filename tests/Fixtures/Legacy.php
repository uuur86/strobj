<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures;

use StrObj\Behavior;
use StrObj\StringObjects;

/** Creates instances with the v2.1 legacy behavior; explicit options take precedence. */
final class Legacy
{
    /**
     * @param mixed $data    Input array, object or JSON document.
     * @param array $options Options passed to StringObjects::instance().
     */
    public static function of($data, array $options = []): StringObjects
    {
        return StringObjects::instance($data, $options + ['behavior' => Behavior::LEGACY]);
    }
}
