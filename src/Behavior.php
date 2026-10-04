<?php

/**
 * This file is part of the StrObj package.
 * (c) Uğur Biçer <contact@codeplus.dev>
 * See LICENSE for copyright and license information.
 */

declare(strict_types=1);

namespace StrObj;

use InvalidArgumentException;

/** Selects a public behavior contract without changing existing applications' defaults. */
final class Behavior
{
    public const LEGACY = 'legacy';
    public const CONSISTENT = 'consistent';

    /** Validates the shared profile option and reports whether strict behavior was requested. */
    public static function isConsistent(array $options): bool
    {
        $profile = $options['behavior'] ?? self::LEGACY;

        if ($profile !== self::LEGACY && $profile !== self::CONSISTENT) {
            throw new InvalidArgumentException('behavior must be legacy or consistent.');
        }

        return $profile === self::CONSISTENT;
    }
}
