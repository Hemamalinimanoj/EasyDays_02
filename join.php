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

$raw  = file_get_contents('php://input', false, null, 0, 8192);
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    out(false, 'Invalid data', 400);
}

$name  = trim(mb_substr((string)($data['name']  ?? ''), 0, 100));
$email = trim(mb_substr((string)($data['email'] ?? ''), 0, 150));
$phone = trim(mb_substr((string)($data['phone'] ?? ''), 0, 20));

if (mb_strlen($name) < 2) { out(false, 'Please enter your name.', 400); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { out(false, 'Please enter a valid email address.', 400); }
$digits = preg_replace('/\D/', '', $phone);
if (!preg_match('/^[0-9+()\-\s]+$/', $phone) || strlen($digits) < 7 || strlen($digits) > 15) {
    out(false, 'Please enter a valid phone number.', 400);
}

try {
    save_contact($name, $email, $phone);
    out(true);
} catch (Throwable $e) {
    error_log('EasyDays join error: ' . $e->getMessage());
    out(false, 'Could not save your details. Please try again.', 500);
}
