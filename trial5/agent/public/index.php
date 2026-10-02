<?php
declare(strict_types=1);
// PHP-FPM front controller (16 workers per node, 4 nodes, shared NFS store).
require __DIR__ . '/../bootstrap.php';

$kernel = new App\Kernel(getenv('STORE_DIR') ?: '/var/lib/storefront');
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('#^/orders/(\d+)/cancellations$#', $path, $m)) {
    /** @var array{lines?: array<int|string, string|int>} $body */
    $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
    header('Content-Type: application/json');
    echo json_encode($kernel->cancellations->cancel(
        (int) $m[1],
        $body['lines'] ?? [],
        (string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '')
    ));
    return;
}
http_response_code(404);
