<?php

/**
 * Runs the project's PHPCBF formatter and treats exit code 1 as successful fixing.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$formatter = $root . '/vendor/squizlabs/php_codesniffer/bin/phpcbf';

if (!is_file($formatter)) {
    fwrite(STDERR, "Run composer install before formatting.\n");
    exit(2);
}

$command = array_merge([PHP_BINARY, $formatter, '--standard=' . $root . '/phpcs.xml'], array_slice($argv, 1));
$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $root);

if (!is_resource($process)) {
    fwrite(STDERR, "Cannot start PHPCBF.\n");
    exit(2);
}

$status = proc_close($process);
exit($status === 1 ? 0 : $status);
