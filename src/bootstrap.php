<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
spl_autoload_register(function (string $class): void {
    $prefix = 'ScholarDesk\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require $file;
    }
});
