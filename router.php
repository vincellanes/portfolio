<?php
/**
 * Local testing only (not used on Vercel).
 *
 *   SMTP_USER=you@gmail.com SMTP_PASS=yourapppassword php -S localhost:8000 router.php
 *
 * then open http://localhost:8000
 * It mimics Vercel: files in public/ are served as they are, /api/*.php runs as PHP.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

if (preg_match('#^/api/[A-Za-z0-9_-]+\.php$#', $path) && is_file(__DIR__ . $path)) {
    require __DIR__ . $path;
    return true;
}

$file = realpath(__DIR__ . '/public' . ($path === '/' ? '/index.html' : $path));
$root = realpath(__DIR__ . '/public');
if ($file !== false && $root !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
    $types = ['html' => 'text/html; charset=utf-8', 'css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'pdf' => 'application/pdf', 'mp4' => 'video/mp4'];
    header('Content-Type: ' . ($types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
    readfile($file);
    return true;
}

http_response_code(404);
echo 'Not found';
return true;
