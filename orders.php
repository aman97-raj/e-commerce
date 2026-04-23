<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';

require_auth();

$action  = $_GET['action'] ?? (request_json()['action'] ?? 'list');
$payload = array_merge($_POST, request_json());
$uid     = (int)$_SESSION['user_id'];

if ($action === 'list') {
    $stmt = db()->prepare("SELECT o.id, o.order_number, o.total_amount, o.status, o.payment_method, o.created_at, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id WHERE o.user_id = :uid GROUP BY o.id ORDER BY o.created_at DESC");
    $stmt->execute(['uid' => $uid]);
    json_response(['success' => true, 'orders' => $stmt->fetchAll()]);
}

if ($action === 'detail') {
    $oid = (int)($_GET['id'] ?? 0);
    $stmt = db()->prepare('SELECT o.*, a.full_name as addr_name, a.address, a.city, a.state, a.pincode, a.phone as addr_phone FROM orders o LEFT JOIN addresses a ON a.id = o.address_id WHERE o.id = :id AND o.user_id = :uid');
    $stmt->execute(['id' => $oid, 'uid' => $uid]);
    $order = $stmt->fetch();
    if (!$order) json_response(['success' => false, 'message' => 'Order not found.'], 404);

    $items = db()->prepare('SELECT oi.*, p.name, p.main_image FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :oid');
    $items->execute(['oid' => $oid]);
    $order['items'] = $items->fetchAll();
    json_response(['success' => true, 'order' => $order]);
}

if ($action === 'place') {
    if (!is_post()) json_response(['success' => false, 'message' => 'POST required.'], 405);

    if (!verify_csrf($payload['csrf_token'] ?? null)) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 422);
    }

    $addressId     = (int)($payload['address_id'] ?? 0);
    $paymentMethod = sanitize_text($payload['payment_method'] ?? 'cod', 20);
    $couponCode    = strtoupper(sanitize_text($payload['coupon_code'] ?? '', 20));
    $discount      = (float)($payload['discount'] ?? 0);

    if ($addressId <= 0) json_response(['success' => false, 'message' => 'Please select a delivery address.'], 422);

    // Verify address belongs to user
    $addrCheck = db()->prepare('SELECT id FROM addresses WHERE id = :id AND user_id = :uid');
    $addrCheck->execute(['id' => $addressId, 'uid' => $uid]);
    if (!$addrCheck->fetch()) json_response(['success' => false, 'message' => 'Invalid address.'], 422);

    // Get cart
    $cartStmt = db()->prepare('SELECT c.quantity, c.product_id, p.price, p.stock, p.name FROM cart c JOIN products p ON p.id = c.product_id WHERE c.user_id = :uid');
    $cartStmt->execute(['uid' => $uid]);
    $cartItems = $cartStmt->fetchAll();

    if (empty($cartItems)) json_response(['success' => false, 'message' => 'Your cart is empty.'], 422);

    $total = 0;
    foreach ($cartItems as $item) {
        if ($item['stock'] < $item['quantity']) {
            json_response(['success' => false, 'message' => "{$item['name']} is out of stock."], 422);
        }
        $total += $item['price'] * $item['quantity'];
    }

    $finalTotal = round($total - $discount, 2);
    $orderNum   = generate_order_number();

    db()->beginTransaction();
    try {
        $oStmt = db()->prepare('INSERT INTO orders (order_number, user_id, address_id, total_amount, discount, coupon_code, payment_method) VALUES (:on, :uid, :aid, :total, :disc, :coup, :pm)');
        $oStmt->execute(['on' => $orderNum, 'uid' => $uid, 'aid' => $addressId, 'total' => $finalTotal, 'disc' => $discount, 'coup' => $couponCode, 'pm' => $paymentMethod]);
        $orderId = (int)db()->lastInsertId();

        $iStmt = db()->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (:oid, :pid, :qty, :price)');
        foreach ($cartItems as $item) {
            $iStmt->execute(['oid' => $orderId, 'pid' => $item['product_id'], 'qty' => $item['quantity'], 'price' => $item['price']]);
            db()->prepare('UPDATE products SET stock = stock - :q WHERE id = :id')->execute(['q' => $item['quantity'], 'id' => $item['product_id']]);
            // Low stock alert
            $newStock = (int)$item['stock'] - (int)$item['quantity'];
            if ($newStock <= 5 && $newStock >= 0) {
                $label = $newStock === 0 ? '❌ OUT OF STOCK' : '⚠️ Low Stock (' . $newStock . ' left)';
                db()->prepare('INSERT OR IGNORE INTO admin_notifications (type,title,body,link) VALUES (?,?,?,?)')
                   ->execute(['stock', $label . ': ' . mb_substr($item['name'],0,35), 'Only ' . $newStock . ' units of "' . $item['name'] . '" remaining. Restock immediately!', 'products.html']);
            }
        }

        // Update coupon usage
        if ($couponCode) {
            db()->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE code = :code')->execute(['code' => $couponCode]);
        }

        // Clear cart
        db()->prepare('DELETE FROM cart WHERE user_id = :uid')->execute(['uid' => $uid]);

        db()->commit();

        // === ADMIN NOTIFICATION: New Order ===
        try {
            $userName = db()->prepare('SELECT full_name FROM users WHERE id = :uid');
            $userName->execute(['uid' => $uid]);
            $uName = $userName->fetchColumn() ?: 'A customer';
            $itemCount = count($cartItems);
            db()->prepare('INSERT INTO admin_notifications (type, title, body, link) VALUES (?,?,?,?)')
               ->execute([
                   'order',
                   '🛒 New Order Received!',
                   $uName . ' placed order ' . $orderNum . ' for ₹' . number_format($finalTotal, 2) . ' (' . $itemCount . ' item' . ($itemCount > 1 ? 's' : '') . ') via ' . strtoupper($paymentMethod),
                   'orders.html'
               ]);
        } catch (\Throwable $e) { /* silent */ }

        json_response(['success' => true, 'message' => 'Order placed!', 'order_number' => $orderNum, 'order_id' => $orderId]);
    } catch (Exception $e) {
        db()->rollBack();
        json_response(['success' => false, 'message' => 'Order placement failed. Please try again.'], 500);
    }
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
