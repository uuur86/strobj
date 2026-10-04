<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    // Allows the official standalone PHPUnit PHAR to run without Composer.
    spl_autoload_register(static function (string $class): void {
        // Test classes are checked first because their prefix is more specific.
        foreach (['StrObj\\Tests\\' => '/tests/', 'StrObj\\' => '/src/'] as $prefix => $directory) {
            if (strpos($class, $prefix) === 0) {
                $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
                $file = dirname(__DIR__) . $directory . $relative . '.php';

                if (is_file($file)) {
                    require $file;
                }

                return;
            }
        }
    });
}
