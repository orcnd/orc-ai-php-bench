<?php
declare(strict_types=1);
spl_autoload_register(function (string $class): void {
    foreach (['App\\' => '/app/', 'Legacy\\' => '/legacy/'] as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = __DIR__ . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    }
});
