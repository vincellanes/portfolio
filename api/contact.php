<?php
/**
 * Contact form handler.
 *
 * The form in public/index.html posts here (/api/contact.php). Vercel runs this file
 * with the vercel-php runtime (see vercel.json) and the message is emailed to you
 * through SMTP using PHPMailer.
 *
 * Why SMTP and not PHP's mail()? Vercel has no mail server, and its disk is read-only,
 * so mail() and writing to messages.txt both fail there. SMTP works anywhere.
 *
 * Your Gmail address and app password are NOT in this file. They are read from
 * Environment Variables that you set in the Vercel dashboard (steps in DEPLOY.md):
 *
 *   SMTP_USER       required  the Gmail address that sends the email (also receives it by default)
 *   SMTP_PASS       required  Gmail "app password" (16 letters, not your normal password)
 *   MAIL_TO         optional  where messages are delivered (default: SMTP_USER)
 *   MAIL_FROM       optional  From address (default: SMTP_USER)
 *   SMTP_HOST       optional  default smtp.gmail.com
 *   SMTP_PORT       optional  default 465 (587 when SMTP_SECURE=tls)
 *   SMTP_SECURE     optional  ssl (default), tls (STARTTLS) or none (only allowed for localhost testing)
 */
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/../lib/phpmailer/Exception.php';
require __DIR__ . '/../lib/phpmailer/PHPMailer.php';
require __DIR__ . '/../lib/phpmailer/SMTP.php';

// Never print PHP errors into the JSON answer; they go to the Vercel function logs instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/** Send the JSON answer that script.js expects ({success, message}) and stop. */
function reply(int $status, bool $success, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Read a setting from the environment (Vercel Environment Variables). */
function setting(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? '';
    }
    $value = trim((string) $value);
    return $value === '' ? $default : $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    reply(405, false, 'method not allowed');
}

// The page sends a normal form post. $_POST is used first; the raw body is a fallback.
$input = $_POST;
if (!$input) {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '') {
        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
        if (str_contains($type, 'application/json')) {
            $decoded = json_decode($raw, true);
            $input = is_array($decoded) ? $decoded : [];
        } else {
            parse_str($raw, $input);
        }
    }
}

function field(array $input, string $key): string
{
    return is_string($input[$key] ?? null) ? $input[$key] : '';
}

// Hidden checkbox that real visitors never tick. Bots do. Pretend it worked and send nothing.
if (field($input, 'botcheck') !== '') {
    reply(200, true, 'Message sent.');
}

// Clean the input. Names and emails go into email headers, so line breaks and control characters are removed.
$name = trim(preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', field($input, 'name')) ?? '');
$email = trim(preg_replace('/[\p{Cc}\p{Cf}\s]+/u', '', field($input, 'email')) ?? '');
$message = preg_replace('/[^\P{Cc}\n\r\t]+/u', '', field($input, 'message')) ?? '';
$message = trim(str_replace(["\r\n", "\r"], "\n", $message));

if ($name === '' || $message === '' || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    reply(400, false, 'please fill in every field with a valid email');
}
if (mb_strlen($name) > 100 || strlen($email) > 254 || mb_strlen($message) > 5000) {
    reply(400, false, 'the name or message is too long (message limit is 5000 characters)');
}

// Mail settings come from the environment.
$user = setting('SMTP_USER');
$pass = setting('SMTP_PASS');
if ($user === '' || $pass === '') {
    error_log('contact.php: SMTP_USER and SMTP_PASS environment variables are not set.');
    reply(500, false, 'the mail service is not set up yet');
}

$host = setting('SMTP_HOST', 'smtp.gmail.com');
$secure = strtolower(setting('SMTP_SECURE', 'ssl'));
if (!in_array($secure, ['ssl', 'tls', 'none'], true)) {
    $secure = 'ssl';
}
if ($secure === 'none' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    error_log('contact.php: SMTP_SECURE=none is only allowed for localhost.');
    reply(500, false, 'the mail service is not set up correctly');
}
$port = (int) setting('SMTP_PORT', $secure === 'tls' ? '587' : ($secure === 'none' ? '25' : '465'));
$to = setting('MAIL_TO', $user);
$from = setting('MAIL_FROM', $user);

$when = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('D, j M Y g:i A') . ' (Philippines time)';
$body = "New message from your portfolio contact form\n\n"
    . "Name:  {$name}\n"
    . "Email: {$email}\n"
    . "Sent:  {$when}\n\n"
    . "Message:\n{$message}\n\n"
    . "-- \nPress Reply to answer {$name} directly.\n";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = $port;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $pass;
    $mail->SMTPSecure = $secure === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : ($secure === 'none' ? '' : PHPMailer::ENCRYPTION_SMTPS);
    $mail->SMTPAutoTLS = $secure !== 'none';
    $mail->Timeout = 10;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;

    $mail->setFrom($from, 'Portfolio contact form');
    $mail->addAddress($to);
    $mail->addReplyTo($email, $name);   // so "Reply" in Gmail answers the visitor
    $mail->isHTML(false);
    $mail->Subject = 'Portfolio message from ' . mb_strimwidth($name, 0, 60, '...');
    $mail->Body = $body;
    $mail->send();
} catch (Throwable $e) {
    error_log('contact.php: could not send mail: ' . ($mail->ErrorInfo ?? '') . ' | ' . $e->getMessage());
    reply(502, false, 'the mail server could not be reached right now');
}

reply(200, true, 'Message sent.');
