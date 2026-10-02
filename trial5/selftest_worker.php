<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';
$kernel = new App\Kernel($argv[1], new App\Support\FrozenClock($argv[2]));
while (microtime(true) < (float) $argv[3]) {
    usleep(200);
}
echo json_encode($kernel->cancellations->cancel((int) $argv[4], json_decode($argv[6], true), $argv[5]));
