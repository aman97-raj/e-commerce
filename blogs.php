<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';

function ensure_blog_schema(): void {
    static $done = false;
    if ($done) return;

    $pdo = db();
    $cols = [];
    foreach ($pdo->query('PRAGMA table_info(blogs)') as $row) {
        $name = (string)($row['name'] ?? '');
        if ($name !== '') $cols[$name] = true;
    }

    if (!isset($cols['category'])) {
        $pdo->exec("ALTER TABLE blogs ADD COLUMN category TEXT DEFAULT 'Style'");
    }
    if (!isset($cols['author_name'])) {
        $pdo->exec("ALTER TABLE blogs ADD COLUMN author_name TEXT DEFAULT 'Hey Buddy Team'");
    }
    if (!isset($cols['media_type'])) {
        $pdo->exec("ALTER TABLE blogs ADD COLUMN media_type TEXT DEFAULT 'none'");
    }
    if (!isset($cols['media_url'])) {
        $pdo->exec("ALTER TABLE blogs ADD COLUMN media_url TEXT DEFAULT ''");
    }

    $done = true;
}

$action = $_GET['action'] ?? 'list';
ensure_blog_schema();

if ($action === 'list') {
    $search = sanitize_text($_GET['search'] ?? '', 120);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(24, max(1, (int)($_GET['limit'] ?? 12)));
    $offset = ($page - 1) * $limit;

    $where = ['is_published = 1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(LOWER(title) LIKE :q OR LOWER(excerpt) LIKE :q OR LOWER(content) LIKE :q OR LOWER(tags) LIKE :q)';
        $params['q'] = '%' . strtolower($search) . '%';
    }

    $whereStr = implode(' AND ', $where);

    $countStmt = db()->prepare("SELECT COUNT(*) as total FROM blogs WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int)($countStmt->fetch()['total'] ?? 0);

    $stmt = db()->prepare("SELECT id,title,slug,excerpt,content,cover_image,category,author_name,media_type,media_url,tags,created_at FROM blogs WHERE $whereStr ORDER BY datetime(created_at) DESC LIMIT :lim OFFSET :off");
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    json_response([
        'success' => true,
        'items' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => (int)ceil($total / $limit),
    ]);
}

if ($action === 'detail') {
    $slug = sanitize_text($_GET['slug'] ?? '', 200);
    $id = (int)($_GET['id'] ?? 0);

    if (!$slug && !$id) {
        json_response(['success' => false, 'message' => 'Blog not found.'], 404);
    }

    $stmt = $slug
        ? db()->prepare('SELECT id,title,slug,excerpt,content,cover_image,category,author_name,media_type,media_url,tags,created_at FROM blogs WHERE is_published = 1 AND slug = :v LIMIT 1')
        : db()->prepare('SELECT id,title,slug,excerpt,content,cover_image,category,author_name,media_type,media_url,tags,created_at FROM blogs WHERE is_published = 1 AND id = :v LIMIT 1');
    $stmt->execute(['v' => $slug ?: $id]);
    $item = $stmt->fetch();

    if (!$item) {
        json_response(['success' => false, 'message' => 'Blog not found.'], 404);
    }

    json_response(['success' => true, 'item' => $item]);
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
