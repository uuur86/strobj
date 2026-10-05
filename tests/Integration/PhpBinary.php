<?php

declare(strict_types=1);

namespace StrObj\Tests\Integration;

/** Resolves the PHP command-line binary for child processes started by integration tests. */
final class PhpBinary
{
    /**
     * Returns the running binary, or the CLI next to phpdbg when coverage runs under phpdbg.
     *
     * @return string
     */
    public static function cli(): string
    {
        if (PHP_SAPI !== 'phpdbg') {
            return PHP_BINARY;
        }

        $extension = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';

        return dirname(PHP_BINARY) . '/php' . $extension;
    }
}
