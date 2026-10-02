<?php
declare(strict_types=1);
spl_autoload_register(function (string $class): void {
    if (strpos($class, 'App\\') === 0) {
        require __DIR__ . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});
