<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $category = sanitize_text($_GET['category'] ?? '', 80);
    $search   = sanitize_text($_GET['search'] ?? '', 120);
    $sort     = $_GET['sort'] ?? 'popular';
    $featured = (int)($_GET['featured'] ?? 0);
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = min(48, max(8, (int)($_GET['limit'] ?? 16)));
    $offset   = ($page - 1) * $limit;

    $where = ['1=1'];
    $params = [];

    if ($category) {
        $where[] = 'LOWER(category) = LOWER(:cat)';
        $params['cat'] = $category;
    }
    if ($search) {
        $where[] = '(LOWER(name) LIKE :q OR LOWER(description) LIKE :q OR LOWER(brand) LIKE :q)';
        $params['q'] = '%' . strtolower($search) . '%';
    }
    if ($featured) {
        $where[] = 'is_featured = 1';
    }

    $orderBy = match($sort) {
        'price_asc'  => 'price ASC',
        'price_desc' => 'price DESC',
        'newest'     => 'created_at DESC',
        'rating'     => 'rating DESC',
        default      => 'review_count DESC, is_featured DESC',
    };

    $whereStr = implode(' AND ', $where);

    $total = (int)db()->prepare("SELECT COUNT(*) FROM products WHERE $whereStr")
        ->execute($params) ? db()->prepare("SELECT COUNT(*) FROM products WHERE $whereStr")->execute($params) : 0;
    
    $countStmt = db()->prepare("SELECT COUNT(*) as total FROM products WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int)($countStmt->fetch()['total'] ?? 0);

    $stmt = db()->prepare("SELECT id,name,slug,category,brand,price,mrp,stock,main_image,rating,review_count,is_featured FROM products WHERE $whereStr ORDER BY $orderBy LIMIT :lim OFFSET :off");
    foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    json_response([
        'success' => true,
        'items'   => $items,
        'total'   => $total,
        'page'    => $page,
        'pages'   => (int)ceil($total / $limit),
    ]);
}

if ($action === 'detail') {
    $slug = sanitize_text($_GET['slug'] ?? '', 200);
    $id   = (int)($_GET['id'] ?? 0);

    if (!$slug && !$id) json_response(['success' => false, 'message' => 'Product not found.'], 404);

    $stmt = $slug
        ? db()->prepare('SELECT * FROM products WHERE slug = :v LIMIT 1')
        : db()->prepare('SELECT * FROM products WHERE id = :v LIMIT 1');
    $stmt->execute(['v' => $slug ?: $id]);
    $product = $stmt->fetch();

    if (!$product) json_response(['success' => false, 'message' => 'Product not found.'], 404);

    // Reviews
    $reviews = db()->prepare('SELECT r.*, u.full_name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = :pid ORDER BY r.created_at DESC LIMIT 10');
    $reviews->execute(['pid' => $product['id']]);
    $product['reviews'] = $reviews->fetchAll();

    // Related
    $related = db()->prepare("SELECT id,name,slug,price,mrp,main_image,rating,review_count FROM products WHERE category = :cat AND id != :id ORDER BY rating DESC LIMIT 6");
    $related->execute(['cat' => $product['category'], 'id' => $product['id']]);
    $product['related'] = $related->fetchAll();

    json_response(['success' => true, 'product' => $product]);
}

if ($action === 'categories') {
    $rows = db()->query('SELECT name, slug, icon FROM categories ORDER BY name')->fetchAll();
    json_response(['success' => true, 'categories' => $rows]);
}

if ($action === 'search_suggestions') {
    $q = sanitize_text($_GET['q'] ?? '', 100);
    if (strlen($q) < 2) json_response(['success' => true, 'suggestions' => []]);

    $stmt = db()->prepare("SELECT name, slug FROM products WHERE LOWER(name) LIKE :q LIMIT 8");
    $stmt->execute(['q' => '%' . strtolower($q) . '%']);
    json_response(['success' => true, 'suggestions' => $stmt->fetchAll()]);
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
