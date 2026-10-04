<?php

/**
 * This file is part of the StrObj package.
 *
 * (c) Uğur Biçer <contact@fyndsoft.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package StrObj
 * @link    https://github.com/uuur86/strobj
 */

declare(strict_types=1);

namespace StrObj;

use InvalidArgumentException;

/**
 * Selects the behavior contract of a StringObjects instance.
 * CONSISTENT is the default; LEGACY reproduces the v2.1 results for existing applications.
 */
final class Behavior
{
    public const LEGACY = 'legacy';
    public const CONSISTENT = 'consistent';

    /** Validates the behavior option and reports whether the consistent contract applies. */
    public static function isConsistent(array $options): bool
    {
        $profile = $options['behavior'] ?? self::CONSISTENT;

        if ($profile !== self::LEGACY && $profile !== self::CONSISTENT) {
            throw new InvalidArgumentException('behavior must be legacy or consistent.');
        }

        return $profile === self::CONSISTENT;
    }
}
