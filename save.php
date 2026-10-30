<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(bool $ok, string $msg = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'error' => $msg]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    out(false, 'POST only', 405);
}

$raw  = file_get_contents('php://input', false, null, 0, 65536);
$data = json_decode($raw ?: '', true);
if (!is_array($data) || !isset($data['answers']) || !is_array($data['answers'])) {
    out(false, 'Invalid data', 400);
}

// Accept only known question IDs, trim and limit length
$vals = [];
foreach (questions() as $id => $_) {
    $v = $data['answers'][$id] ?? '';
    $v = is_string($v) ? trim(mb_substr($v, 0, 2000)) : '';
    $vals[$id] = ($v === '') ? null : $v;
}
if (count(array_filter($vals, fn($v) => $v !== null)) === 0) {
    out(false, 'Empty response', 400);
}

// Every question is mandatory.
foreach ($vals as $v) {
    if ($v === null) out(false, 'Please answer all questions', 400);
}

try {
    save_response($vals);
    out(true);
} catch (Throwable $e) {
    error_log('EasyDays save error: ' . $e->getMessage());
    out(false, db_hint($e), 500);
}
