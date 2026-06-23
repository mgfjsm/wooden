<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Rate limiting
checkRateLimit();

 $db = null;
function getDB() {
    global $db;
    if ($db === null) {
        $db = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
    return $db;
}

function jr($data, $code = 200) { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function san($s) { return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8'); }
function getClientIP() { return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function hashIP($ip) { return hash('sha256', $ip . '::' . JWT_SECRET); }

function checkRateLimit() {
    $ip = hashIP(getClientIP());
    $d = getDB();
    $s = $d->prepare("SELECT requests, window_start FROM rate_limits WHERE ip_hash = ?");
    $s->execute([$ip]);
    $row = $s->fetch();
    if (!$row) {
        $d->prepare("INSERT INTO rate_limits (ip_hash) VALUES (?)")->execute([$ip]);
        return;
    }
    $since = (time() - strtotime($row['window_start']));
    if ($since > RATE_WINDOW) {
        $d->prepare("UPDATE rate_limits SET requests=1, window_start=NOW() WHERE ip_hash=?")->execute([$ip]);
        return;
    }
    if ($row['requests'] >= RATE_LIMIT) {
        jr(['success'=>false,'error'=>'Rate limit exceeded. Try again in '.(RATE_WINDOW-$since).'s'], 429);
    }
    $d->prepare("UPDATE rate_limits SET requests=requests+1 WHERE ip_hash=?")->execute([$ip]);
}

function verifyAdmin() {
    $h = getallheaders();
    $token = str_replace('Bearer ', '', $h['Authorization'] ?? '');
    if (!$token) jr(['success'=>false,'error'=>'Unauthorized'], 401);
    $parts = explode('.', $token);
    if (count($parts) !== 3) jr(['success'=>false,'error'=>'Invalid token'], 401);
    $payload = json_decode(base64_decode($parts[1]), true);
    if (!$payload || ($payload['exp'] ?? 0) < time()) jr(['success'=>false,'error'=>'Token expired'], 401);
    $sig = base64_encode(hash_hmac('sha256', $parts[0].'.'.$parts[1], JWT_SECRET, true));
    if ($sig !== $parts[2]) jr(['success'=>false,'error'=>'Invalid token signature'], 401);
    return $payload;
}

function makeToken($user, $expiry) {
    $h = base64_encode(json_encode(['typ'=>'JWT','alg'=>'HS256']));
    $p = base64_encode(json_encode(['user'=>$user,'exp'=>time()+$expiry,'iat'=>time(),'jti'=>bin2hex(random_bytes(16))]));
    $s = base64_encode(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    return "$h.$p.$s";
}

function logActivity($action, $details = null) {
    try {
        $p = verifyAdmin();
        $d = getDB();
        $s = $d->prepare("INSERT INTO activity_log (admin_id, action, details, ip_hash) VALUES (?,?,?,?)");
        $s->execute([$p['admin_id'] ?? null, $action, json_encode($details), hashIP(getClientIP())]);
    } catch (Exception $e) {}
}

function uploadFile($subdir = '') {
    if (!isset($_FILES['image'])) jr(['success'=>false,'error'=>'No file'], 400);
    $f = $_FILES['image'];
    if ($f['error'] !== UPLOAD_ERR_OK) jr(['success'=>false,'error'=>'Upload error'], 400);
    if ($f['size'] > MAX_UPLOAD_SIZE) jr(['success'=>false,'error'=>'File too large'], 400);
    if (!in_array($f['type'], ALLOWED_TYPES)) jr(['success'=>false,'error'=>'Invalid type'], 400);
    $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$f['type']];
    $dir = UPLOAD_DIR . ($subdir ? $subdir.'/' : '');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = uniqid('mn_') . '.' . $ext;
    move_uploaded_file($f['tmp_name'], $dir . $name);
    return UPLOAD_URL . ($subdir ? $subdir.'/' : '') . $name;
}

// ============ ROUTES ============
 $action = $_GET['action'] ?? '';
switch ($action) {
    case 'login': handleLogin(); break;
    case 'refresh_token': handleRefresh(); break;
    case 'change_password': verifyAdmin(); handleChangePassword(); break;
    case 'get_site_data': getSiteData($_GET['section'] ?? 'all'); break;
    case 'save_site_data': verifyAdmin(); saveSiteData(); break;
    case 'upload_image': verifyAdmin(); $sub = $_GET['subdir'] ?? 'collections'; jr(['success'=>true,'url'=>uploadFile($sub)]); break;
    case 'get_collections': getCollections(); break;
    case 'create_collection': verifyAdmin(); createCollection(); break;
    case 'update_collection': verifyAdmin(); updateCollection(); break;
    case 'delete_collection': verifyAdmin(); deleteCollection(); break;
    case 'get_activity_log': verifyAdmin(); getActivityLog(); break;
    default: jr(['success'=>false,'error'=>'Unknown action'], 400);
}

function handleLogin() {
    $in = json_decode(file_get_contents('php://input'), true);
    $u = $in['username'] ?? ''; $p = $in['password'] ?? '';
    $d = getDB();
    $s = $d->prepare("SELECT * FROM admins WHERE username=? AND is_active=1");
    $s->execute([$u]); $row = $s->fetch();
    if (!$row || !password_verify($p, $row['password_hash'])) jr(['success'=>false,'error'=>'Invalid credentials'], 401);
    $d->prepare("UPDATE admins SET last_login=NOW() WHERE id=?")->execute([$row['id']]);
    logActivity('login', ['admin_id'=>$row['id']]);
    jr(['success'=>true,'token'=>makeToken($u, JWT_EXPIRY),'refresh'=>makeToken($u, REFRESH_EXPIRY),'user'=>$u,'display_name'=>$row['display_name']]);
}

function handleRefresh() {
    $in = json_decode(file_get_contents('php://input'), true);
    $token = $in['refresh_token'] ?? '';
    $parts = explode('.', $token);
    if (count($parts) !== 3) jr(['success'=>false,'error'=>'Invalid'], 401);
    $p = json_decode(base64_decode($parts[1]), true);
    if (!$p || ($p['exp'] ?? 0) < time()) jr(['success'=>false,'error'=>'Expired'], 401);
    $sig = base64_encode(hash_hmac('sha256', $parts[0].'.'.$parts[1], JWT_SECRET, true));
    if ($sig !== $parts[2]) jr(['success'=>false,'error'=>'Invalid'], 401);
    jr(['success'=>true,'token'=>makeToken($p['user'], JWT_EXPIRY)]);
}

function handleChangePassword() {
    $p = verifyAdmin();
    $in = json_decode(file_get_contents('php://input'), true);
    $old = $in['old_password'] ?? ''; $new = $in['new_password'] ?? '';
    if (strlen($new) < 8) jr(['success'=>false,'error'=>'Password must be 8+ characters'], 400);
    $d = getDB();
    $s = $d->prepare("SELECT password_hash FROM admins WHERE username=?", [$p['user']]);
    $s->execute([$p['user']]); $row = $s->fetch();
    if (!password_verify($old, $row['password_hash'])) jr(['success'=>false,'error'=>'Current password incorrect'], 403);
    $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>BCRYPT_COST]);
    $d->prepare("UPDATE admins SET password_hash=? WHERE username=?")->execute([$hash, $p['user']]);
    logActivity('change_password');
    jr(['success'=>true,'message'=>'Password updated']);
}

function getSiteData($section) {
    $d = getDB();
    if ($section === 'all') {
        $s = $d->query("SELECT key_name, data_json FROM site_data");
        $data = [];
        while ($row = $s->fetch()) $data[$row['key_name']] = json_decode($row['data_json'], true);
        // Also get collections
        $cs = $d->query("SELECT * FROM collections ORDER BY sort_order ASC, id ASC");
        $cols = [];
        while ($c = $cs->fetch()) { $c['gallery'] = json_decode($c['gallery']??'[]',true)?:[]; $c['is_limited']=(int)$c['is_limited']; $c['pieces_count']=(int)$c['pieces_count']; $c['sort_order']=(int)$c['sort_order']; $cols[]=$c; }
        $data['collections'] = $cols;
        jr(['success'=>true,'data'=>$data]);
    } else {
        $s = $d->prepare("SELECT data_json FROM site_data WHERE key_name=?");
        $s->execute([$section]); $row = $s->fetch();
        if (!$row) jr(['success'=>true,'data'=>null]);
        jr(['success'=>true,'data'=>json_decode($row['data_json'],true)]);
    }
}

function saveSiteData() {
    $in = json_decode(file_get_contents('php://input'), true);
    $section = $in['section'] ?? ''; $data = $in['data'] ?? null;
    if (!$section || $data === null) jr(['success'=>false,'error'=>'Missing section or data'], 400);
    $d = getDB();
    $s = $d->prepare("INSERT INTO site_data (key_name, data_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE data_json = VALUES(data_json), updated_at = NOW()");
    $s->execute([$section, json_encode($data, JSON_UNESCAPED_UNICODE)]);
    logActivity('save_section', ['section'=>$section]);
    jr(['success'=>true,'message'=>"${section} saved"]);
}

function getCollections() {
    $d = getDB();
    $s = $d->query("SELECT * FROM collections ORDER BY sort_order ASC, id ASC");
    $cols = [];
    while ($c = $s->fetch()) { $c['gallery'] = json_decode($c['gallery']??'[]',true)?:[]; $c['is_limited']=(int)$c['is_limited']; $c['pieces_count']=(int)$c['pieces_count']; $c['sort_order']=(int)$c['sort_order']; $cols[]=$c; }
    jr(['success'=>true,'data'=>$cols]);
}

function createCollection() {
    $in = json_decode(file_get_contents('php://input'), true);
    $d = getDB();
    $s = $d->prepare("INSERT INTO collections (title,subtitle,year,description,long_description,pieces_count,is_limited,image_url,gallery,materials,lead_time,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $s->execute([san($in['title']??''),san($in['subtitle']??''),san($in['year']??''),san($in['description']??''),san($in['long_description']??''),(int)($in['pieces_count']??0),(int)($in['is_limited']??0),san($in['image_url']??''),json_encode($in['gallery']??[]),san($in['materials']??''),san($in['lead_time']??''),(int)($in['sort_order']??0)]);
    $id = $d->lastInsertId();
    logActivity('create_collection',['id'=>$id]);
    $r = $d->prepare("SELECT * FROM collections WHERE id=?"); $r->execute([$id]); $row=$r->fetch();
    $row['gallery']=json_decode($row['gallery']??'[]',true)?:[]; $row['is_limited']=(int)$row['is_limited']; $row['pieces_count']=(int)$row['pieces_count']; $row['sort_order']=(int)$row['sort_order'];
    jr(['success'=>true,'data'=>$row],201);
}

function updateCollection() {
    $in = json_decode(file_get_contents('php://input'), true);
    $id = (int)($in['id']??0); if (!$id) jr(['success'=>false,'error'=>'Missing ID'],400);
    $d = getDB();
    $s = $d->prepare("UPDATE collections SET title=?,subtitle=?,year=?,description=?,long_description=?,pieces_count=?,is_limited=?,image_url=?,gallery=?,materials=?,lead_time=?,sort_order=?,updated_at=NOW() WHERE id=?");
    $s->execute([san($in['title']??''),san($in['subtitle']??''),san($in['year']??''),san($in['description']??''),san($in['long_description']??''),(int)($in['pieces_count']??0),(int)($in['is_limited']??0),san($in['image_url']??''),json_encode($in['gallery']??[]),san($in['materials']??''),san($in['lead_time']??''),(int)($in['sort_order']??0),$id]);
    logActivity('update_collection',['id'=>$id]);
    $r = $d->prepare("SELECT * FROM collections WHERE id=?"); $r->execute([$id]); $row=$r->fetch();
    if (!$row) jr(['success'=>false,'error'=>'Not found'],404);
    $row['gallery']=json_decode($row['gallery']??'[]',true)?:[]; $row['is_limited']=(int)$row['is_limited']; $row['pieces_count']=(int)$row['pieces_count']; $row['sort_order']=(int)$row['sort_order'];
    jr(['success'=>true,'data'=>$row]);
}

function deleteCollection() {
    $in = json_decode(file_get_contents('php://input'), true);
    $id = (int)($in['id']??0); if (!$id) jr(['success'=>false,'error'=>'Missing ID'],400);
    $d = getDB();
    $s = $d->prepare("DELETE FROM collections WHERE id=?"); $s->execute([$id]);
    if ($s->rowCount()===0) jr(['success'=>false,'error'=>'Not found'],404);
    logActivity('delete_collection',['id'=>$id]);
    jr(['success'=>true,'message'=>'Deleted']);
}

function getActivityLog() {
    $d = getDB();
    $s = $d->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 100");
    jr(['success'=>true,'data'=>$s->fetchAll()]);
}
