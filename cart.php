<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';

$action  = $_GET['action'] ?? (request_json()['action'] ?? 'get');
$payload = array_merge($_POST, request_json());
$uid     = $_SESSION['user_id'] ?? null;
$sid     = session_id();

function cart_key(): array {
    global $uid, $sid;
    return $uid ? ['user_id' => $uid, 'session_id' => null] : ['user_id' => null, 'session_id' => $sid];
}

function get_cart(): array {
    global $uid, $sid;
    $where  = $uid ? 'c.user_id = :uid' : 'c.session_id = :sid';
    $param  = $uid ? ['uid' => $uid] : ['sid' => $sid];
    $stmt   = db()->prepare("SELECT c.id, c.product_id, c.quantity, p.name, p.price, p.mrp, p.stock, p.main_image, p.slug FROM cart c JOIN products p ON p.id = c.product_id WHERE $where");
    $stmt->execute($param);
    $items = $stmt->fetchAll();
    $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
    $savings = array_sum(array_map(fn($i) => max(0, ($i['mrp'] - $i['price'])) * $i['quantity'], $items));
    return ['items' => $items, 'total' => round($total, 2), 'savings' => round($savings, 2), 'count' => count($items)];
}

if ($action === 'get') {
    json_response(['success' => true, 'cart' => get_cart()]);
}

if ($action === 'add') {
    $pid = (int)($payload['product_id'] ?? 0);
    $qty = max(1, (int)($payload['quantity'] ?? 1));

    if ($pid <= 0) json_response(['success' => false, 'message' => 'Invalid product.'], 422);

    $pStmt = db()->prepare('SELECT id, stock FROM products WHERE id = :id');
    $pStmt->execute(['id' => $pid]);
    $product = $pStmt->fetch();

    if (!$product) json_response(['success' => false, 'message' => 'Product not found.'], 404);
    if ($product['stock'] < 1) json_response(['success' => false, 'message' => 'Out of stock.'], 422);

    if ($uid) {
        $stmt = db()->prepare('INSERT INTO cart (user_id, product_id, quantity) VALUES (:u, :p, :q) ON CONFLICT(user_id, product_id) DO UPDATE SET quantity = quantity + :q');
        $stmt->execute(['u' => $uid, 'p' => $pid, 'q' => $qty]);
    } else {
        $stmt = db()->prepare('INSERT INTO cart (session_id, product_id, quantity) VALUES (:s, :p, :q) ON CONFLICT DO NOTHING');
        $stmt->execute(['s' => $sid, 'p' => $pid, 'q' => $qty]);
    }

    json_response(['success' => true, 'message' => 'Added to cart!', 'cart' => get_cart()]);
}

if ($action === 'update') {
    $cid = (int)($payload['cart_id'] ?? 0);
    $qty = max(1, (int)($payload['quantity'] ?? 1));

    db()->prepare('UPDATE cart SET quantity = :q WHERE id = :id')->execute(['q' => $qty, 'id' => $cid]);
    json_response(['success' => true, 'cart' => get_cart()]);
}

if ($action === 'remove') {
    $cid = (int)($payload['cart_id'] ?? 0);
    db()->prepare('DELETE FROM cart WHERE id = :id')->execute(['id' => $cid]);
    json_response(['success' => true, 'cart' => get_cart()]);
}

if ($action === 'clear') {
    if ($uid) db()->prepare('DELETE FROM cart WHERE user_id = :u')->execute(['u' => $uid]);
    else db()->prepare('DELETE FROM cart WHERE session_id = :s')->execute(['s' => $sid]);
    json_response(['success' => true, 'cart' => get_cart()]);
}

if ($action === 'coupon') {
    $code = strtoupper(sanitize_text($payload['code'] ?? '', 20));
    $total = (float)($payload['total'] ?? 0);

    $stmt = db()->prepare("SELECT * FROM coupons WHERE code = :code AND is_active = 1 AND (expires_at IS NULL OR expires_at > datetime('now')) AND used_count < max_uses");
    $stmt->execute(['code' => $code]);
    $coupon = $stmt->fetch();

    if (!$coupon) json_response(['success' => false, 'message' => 'Invalid or expired coupon.'], 422);
    if ($total < $coupon['min_order']) json_response(['success' => false, 'message' => "Min order ₹{$coupon['min_order']} required."], 422);

    $discount = $coupon['type'] === 'percent' ? round($total * $coupon['value'] / 100, 2) : $coupon['value'];
    json_response(['success' => true, 'discount' => $discount, 'message' => "Coupon applied! You save ₹$discount"]);
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
