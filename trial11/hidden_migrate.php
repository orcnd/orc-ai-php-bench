<?php
declare(strict_types=1);
require $argv[1] . '/bootstrap.php';
echo (new App\Migration\CustomerMigration(new App\Storage\FileStore($argv[2])))->run();
