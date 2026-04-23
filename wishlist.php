<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';

require_auth();

$action  = $_GET['action'] ?? (request_json()['action'] ?? 'get');
$payload = array_merge($_POST, request_json());
$uid     = (int)$_SESSION['user_id'];

if ($action === 'get') {
    $stmt = db()->prepare('SELECT w.id, w.product_id, p.name, p.price, p.mrp, p.main_image, p.slug, p.rating, p.stock FROM wishlist w JOIN products p ON p.id = w.product_id WHERE w.user_id = :uid ORDER BY w.created_at DESC');
    $stmt->execute(['uid' => $uid]);
    json_response(['success' => true, 'items' => $stmt->fetchAll()]);
}

if ($action === 'toggle') {
    $pid = (int)($payload['product_id'] ?? 0);
    if ($pid <= 0) json_response(['success' => false, 'message' => 'Invalid product.'], 422);

    $check = db()->prepare('SELECT id FROM wishlist WHERE user_id = :u AND product_id = :p');
    $check->execute(['u' => $uid, 'p' => $pid]);
    $existing = $check->fetch();

    if ($existing) {
        db()->prepare('DELETE FROM wishlist WHERE id = :id')->execute(['id' => $existing['id']]);
        json_response(['success' => true, 'action' => 'removed', 'message' => 'Removed from wishlist.']);
    } else {
        db()->prepare('INSERT OR IGNORE INTO wishlist (user_id, product_id) VALUES (:u, :p)')->execute(['u' => $uid, 'p' => $pid]);
        json_response(['success' => true, 'action' => 'added', 'message' => 'Added to wishlist!']);
    }
}

if ($action === 'check') {
    $pid = (int)($_GET['product_id'] ?? 0);
    $check = db()->prepare('SELECT id FROM wishlist WHERE user_id = :u AND product_id = :p');
    $check->execute(['u' => $uid, 'p' => $pid]);
    json_response(['success' => true, 'in_wishlist' => (bool)$check->fetch()]);
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
