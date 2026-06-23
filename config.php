<?php
// config.php — Secure Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'maison_noire');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('JWT_SECRET', bin2hex(random_bytes(32))); // Regenerates each request in prod — set a fixed value
define('JWT_EXPIRY', 3600);        // 1 hour access token
define('REFRESH_EXPIRY', 604800);  // 7 day refresh token
define('BCRYPT_COST', 12);         // Higher = slower but safer
define('RATE_LIMIT', 15);           // Max requests per minute per IP
define('RATE_WINDOW', 60);          // Seconds
define('CSRF_TOKEN_LIFE', 3600);
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', './uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('SESSION_TIMEOUT', 1800); // 30 min inactivity logout

if (!is_dir(UPLOAD_DIR . 'collections/')) mkdir(UPLOAD_DIR . 'collections/', 0755, true);
if (!is_dir(UPLOAD_DIR . 'hero/'))       mkdir(UPLOAD_DIR . 'hero/', 0755, true);
if (!is_dir(UPLOAD_DIR . 'story/'))      mkdir(UPLOAD_DIR . 'story/', 0755, true);
if (!is_dir(UPLOAD_DIR . 'gallery/'))    mkdir(UPLOAD_DIR . 'gallery/', 0755, true);
if (!is_dir(UPLOAD_DIR . 'testimonials/')) mkdir(UPLOAD_DIR . 'testimonials/', 0755, true);
if (!is_dir(UPLOAD_DIR . 'quote/'))      mkdir(UPLOAD_DIR . 'quote/', 0755, true);
