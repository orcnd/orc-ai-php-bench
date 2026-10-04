<?php
declare(strict_types=1);
ini_set('memory_limit', '64M');
require $argv[1] . '/bootstrap.php';
$cases = json_decode((string) file_get_contents('php://stdin'), true);
$out = [];
foreach ($cases as [$input, $rules]) {
    $out[] = (new App\Text\Tokenizer())->tokenize($input, $rules);
}
echo json_encode($out);
