<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';

require_auth();

$action  = $_GET['action'] ?? (request_json()['action'] ?? 'get');
$payload = array_merge($_POST, request_json());
$uid     = (int)$_SESSION['user_id'];

if ($action === 'get') {
    $pid  = (int)($_GET['product_id'] ?? 0);
    $stmt = db()->prepare('SELECT r.*, u.full_name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = :pid ORDER BY r.created_at DESC');
    $stmt->execute(['pid' => $pid]);
    json_response(['success' => true, 'reviews' => $stmt->fetchAll()]);
}

if ($action === 'submit') {
    if (!is_post()) json_response(['success' => false, 'message' => 'POST required.'], 405);

    $pid    = (int)($payload['product_id'] ?? 0);
    $rating = (int)($payload['rating'] ?? 0);
    $title  = sanitize_text($payload['title'] ?? '', 120);
    $body   = sanitize_text($payload['body'] ?? '', 1000);

    if ($pid <= 0 || $rating < 1 || $rating > 5) {
        json_response(['success' => false, 'message' => 'Invalid review data.'], 422);
    }

    $stmt = db()->prepare('INSERT INTO reviews (product_id, user_id, rating, title, body) VALUES (:p, :u, :r, :t, :b) ON CONFLICT(user_id, product_id) DO UPDATE SET rating = :r, title = :t, body = :b');
    $stmt->execute(['p' => $pid, 'u' => $uid, 'r' => $rating, 't' => $title, 'b' => $body]);

    // Update product rating
    $avgStmt = db()->prepare('SELECT AVG(rating) as avg, COUNT(*) as cnt FROM reviews WHERE product_id = :pid');
    $avgStmt->execute(['pid' => $pid]);
    $avg = $avgStmt->fetch();
    db()->prepare('UPDATE products SET rating = :r, review_count = :c WHERE id = :id')
       ->execute(['r' => round((float)$avg['avg'], 1), 'c' => (int)$avg['cnt'], 'id' => $pid]);

    // === ADMIN NOTIFICATION: New Review ===
    try {
        $pName = db()->prepare('SELECT name FROM products WHERE id = :pid');
        $pName->execute(['pid' => $pid]);
        $productName = $pName->fetchColumn() ?: 'a product';
        $uName2 = db()->prepare('SELECT full_name FROM users WHERE id = :uid');
        $uName2->execute(['uid' => $uid]);
        $reviewerName = $uName2->fetchColumn() ?: 'A customer';
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        db()->prepare('INSERT INTO admin_notifications (type, title, body, link) VALUES (?,?,?,?)')
           ->execute([
               'review',
               '⭐ New Review on ' . mb_substr($productName, 0, 30),
               $reviewerName . ' gave ' . $stars . ' — "' . mb_substr($title ?: $body, 0, 60) . '"',
               'products.html'
           ]);
    } catch (\Throwable $e) { /* silent */ }

    json_response(['success' => true, 'message' => 'Review submitted! Thank you.']);
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
