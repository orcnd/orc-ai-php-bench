<?php
// Trial 005 concurrency worker: one simulated PHP-FPM request.
// argv: workspace storeDir clockIso startAt orderId key linesJson
declare(strict_types=1);
require $argv[1] . '/bootstrap.php';
$kernel = new App\Kernel($argv[2], new App\Support\FrozenClock($argv[3]));
$lines = json_decode($argv[7], true);
while (microtime(true) < (float) $argv[4]) {
    usleep(200);
}
try {
    echo json_encode($kernel->cancellations->cancel((int) $argv[5], $lines, $argv[6]));
} catch (Throwable $error) {
    echo json_encode(['error' => get_class($error), 'message' => $error->getMessage()]);
}
