<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    // Allows the official standalone PHPUnit PHAR to run without Composer.
    spl_autoload_register(static function (string $class): void {
        $prefix = 'StrObj\\';

        if (strpos($class, $prefix) === 0) {
            $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

            if (is_file($file)) {
                require $file;
            }
        }
    });
}

require_once __DIR__ . '/Fixtures/Legacy.php';
