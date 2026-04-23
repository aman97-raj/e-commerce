<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';

$action = $_GET['action'] ?? $_POST['action'] ?? (request_json()['action'] ?? 'status');

if ($action === 'status') {
    $user = current_user();
    json_response([
        'success'       => true,
        'authenticated' => $user !== null,
        'user'          => $user,
        'membership'    => $user ? membership_snapshot((int)$user['id']) : null,
        'csrf'          => csrf_token(),
    ]);
}

if ($action === 'register') {
    if (!is_post()) json_response(['success' => false, 'message' => 'POST required.'], 405);
    $payload = array_merge($_POST, request_json());

    if (!verify_csrf($payload['csrf_token'] ?? null)) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 422);
    }

    $name  = sanitize_text($payload['full_name'] ?? '', 120);
    $email = strtolower(sanitize_email($payload['email'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string)($payload['phone'] ?? ''));
    $pass  = (string)($payload['password'] ?? '');

    if (!$name || !$email || strlen($pass) < 6 || strlen($phone) !== 10) {
        json_response(['success' => false, 'message' => 'Please provide valid name, email, phone and password (min 6 chars).'], 422);
    }

    $existing = db()->prepare('SELECT id FROM users WHERE email = :email');
    $existing->execute(['email' => $email]);
    if ($existing->fetch()) {
        json_response(['success' => false, 'message' => 'Email already registered. Please login.'], 409);
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = db()->prepare('INSERT INTO users (full_name, email, phone, password_hash) VALUES (:n, :e, :p, :h)');
    $stmt->execute(['n' => $name, 'e' => $email, 'p' => $phone, 'h' => $hash]);

    $uid = (int)db()->lastInsertId();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $uid;

    // === ADMIN NOTIFICATION: New User ===
    try {
        db()->prepare('INSERT INTO admin_notifications (type, title, body, link) VALUES (?,?,?,?)')
           ->execute([
               'user',
               '👤 New Customer Registered!',
               $name . ' (' . $email . ') just created an account',
               'users.html'
           ]);
    } catch (\Throwable $e) { /* silent */ }

    json_response([
        'success' => true,
        'message' => 'Account created successfully!',
        'user' => ['id' => $uid, 'full_name' => $name, 'email' => $email, 'phone' => $phone],
        'membership' => membership_snapshot($uid),
    ]);
}

if ($action === 'login') {
    if (!is_post()) json_response(['success' => false, 'message' => 'POST required.'], 405);
    $payload = array_merge($_POST, request_json());

    if (!verify_csrf($payload['csrf_token'] ?? null)) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 422);
    }

    $email = strtolower(sanitize_email($payload['email'] ?? ''));
    $pass  = (string)($payload['password'] ?? '');

    $stmt = db()->prepare('SELECT id, full_name, email, password_hash FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, (string)$user['password_hash'])) {
        json_response(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];

    json_response([
        'success' => true,
        'message' => 'Login successful!',
        'user' => ['id' => $user['id'], 'full_name' => $user['full_name'], 'email' => $user['email']],
        'membership' => membership_snapshot((int)$user['id']),
    ]);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    json_response(['success' => true, 'message' => 'Logged out.']);
}

if ($action === 'profile') {
    require_auth();
    $user = current_user();
    json_response(['success' => true, 'user' => $user, 'membership' => membership_snapshot((int)$user['id'])]);
}

if ($action === 'update_profile') {
    require_auth();
    if (!is_post()) json_response(['success' => false, 'message' => 'POST required.'], 405);
    $payload = array_merge($_POST, request_json());

    $name  = sanitize_text($payload['full_name'] ?? '', 120);
    $phone = sanitize_text($payload['phone'] ?? '', 15);

    db()->prepare('UPDATE users SET full_name = :n, phone = :p WHERE id = :id')
       ->execute(['n' => $name, 'p' => $phone, 'id' => $_SESSION['user_id']]);

    json_response(['success' => true, 'message' => 'Profile updated.']);
}

json_response(['success' => false, 'message' => 'Invalid action.'], 400);
