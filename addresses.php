<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';

require_auth();

$action  = $_GET['action'] ?? (request_json()['action'] ?? 'list');
$payload = array_merge($_POST, request_json());
$uid     = (int)$_SESSION['user_id'];

if ($action === 'list') {
    $stmt = db()->prepare('SELECT * FROM addresses WHERE user_id = :uid ORDER BY is_default DESC, created_at DESC');
    $stmt->execute(['uid' => $uid]);
    json_response(['success' => true, 'addresses' => $stmt->fetchAll()]);
}

if ($action === 'add') {
    if (!is_post()) json_response(['success' => false, 'message' => 'POST required.'], 405);

    $name    = sanitize_text($payload['full_name'] ?? '', 120);
    $phone   = sanitize_text($payload['phone'] ?? '', 15);
    $address = sanitize_text($payload['address'] ?? '', 300);
    $city    = sanitize_text($payload['city'] ?? '', 80);
    $state   = sanitize_text($payload['state'] ?? '', 80);
    $pin     = sanitize_text($payload['pincode'] ?? '', 10);
    $label   = sanitize_text($payload['label'] ?? 'Home', 20);

    if (!$name || !$phone || !$address || !$city || !$state || !$pin) {
        json_response(['success' => false, 'message' => 'All address fields are required.'], 422);
    }

    $isDefault = (int)($payload['is_default'] ?? 0);
    if ($isDefault) {
        db()->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :uid')->execute(['uid' => $uid]);
    }

    $stmt = db()->prepare('INSERT INTO addresses (user_id, label, full_name, phone, address, city, state, pincode, is_default) VALUES (:uid, :lbl, :n, :ph, :addr, :city, :state, :pin, :def)');
    $stmt->execute(['uid' => $uid, 'lbl' => $label, 'n' => $name, 'ph' => $phone, 'addr' => $address, 'city' => $city, 'state' => $state, 'pin' => $pin, 'def' => $isDefault]);

    json_response(['success' => true, 'message' => 'Address saved!', 'id' => (int)db()->lastInsertId()]);
}

if ($action === 'delete') {
    $id = (int)($payload['id'] ?? 0);
    db()->prepare('DELETE FROM addresses WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
    json_response(['success' => true, 'message' => 'Address removed.']);
}

if ($action === 'set_default') {
    $id = (int)($payload['id'] ?? 0);
    db()->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :uid')->execute(['uid' => $uid]);
    db()->prepare('UPDATE addresses SET is_default = 1 WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
    json_response(['success' => true, 'message' => 'Default address updated.']);
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
