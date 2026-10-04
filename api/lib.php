<?php
// Общие функции для api/order.php и admin/api.php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
define('PRIVATE_DIR', ROOT . '/private');
define('UPLOAD_DIR', ROOT . '/uploads');

function cfg(): array
{
    static $c = null;
    if ($c === null) {
        $f = PRIVATE_DIR . '/config.php';
        $c = is_file($f) ? (array)(require $f) : [];
    }
    return $c;
}

function read_json(string $file, $default = [])
{
    if (!is_file($file)) return $default;
    $d = json_decode((string)file_get_contents($file), true);
    return is_array($d) ? $d : $default;
}

function write_json(string $file, $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $tmp = $file . '.tmp' . bin2hex(random_bytes(3));
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, $file);
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function site_url(): string
{
    $u = (string)(cfg()['site_url'] ?? '');
    if ($u !== '') return rtrim($u, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** HTTP-запрос через cURL. Возвращает [код, тело|null]. */
function http_request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 12): array
{
    if (!function_exists('curl_init')) return [0, null];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $res === false ? null : (string)$res];
}

function telegram_notify(string $text): void
{
    $c = cfg();
    $token = (string)($c['telegram_bot_token'] ?? '');
    $chat = (string)($c['telegram_chat_id'] ?? '');
    if ($token === '' || $chat === '') return;
    http_request(
        'POST',
        "https://api.telegram.org/bot{$token}/sendMessage",
        ['Content-Type: application/json'],
        json_encode(['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true], JSON_UNESCAPED_UNICODE),
        8
    );
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Создаёт платёжную ссылку в эквайринге Точка Банка.
 * Возвращает ['url' => ..., 'id' => ...] или null.
 */
function tochka_create_payment(float $amount, string $purpose, string $orderNumber): ?array
{
    $c = cfg();
    $token = (string)($c['tochka_token'] ?? '');
    $customer = (string)($c['tochka_customer_code'] ?? '');
    if ($token === '' || $customer === '') return null;

    $site = site_url();
    $data = [
        'customerCode' => $customer,
        'amount' => round($amount, 2),
        'purpose' => $purpose,
        'redirectUrl' => $site . '/thanks.html?order=' . rawurlencode($orderNumber),
        'failRedirectUrl' => $site . '/thanks.html?order=' . rawurlencode($orderNumber) . '&fail=1',
        'paymentMode' => ['sbp', 'card'],
    ];
    if (!empty($c['tochka_merchant_id'])) $data['merchantId'] = (string)$c['tochka_merchant_id'];

    [$code, $body] = http_request(
        'POST',
        'https://enter.tochka.com/uapi/acquiring/v1.0/payments',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'],
        json_encode(['Data' => $data], JSON_UNESCAPED_UNICODE)
    );
    $j = $body ? json_decode($body, true) : null;
    $url = $j['Data']['paymentLink'] ?? null;
    if ($code >= 200 && $code < 300 && $url) {
        return ['url' => (string)$url, 'id' => (string)($j['Data']['operationId'] ?? '')];
    }
    error_log('Tochka payment error: HTTP ' . $code . ' ' . substr((string)$body, 0, 500));
    return null;
}

/** Статус платежа в Точке: APPROVED / CREATED / EXPIRED ... или null. */
function tochka_payment_status(string $operationId): ?string
{
    $token = (string)(cfg()['tochka_token'] ?? '');
    if ($token === '' || $operationId === '') return null;
    [$code, $body] = http_request(
        'GET',
        'https://enter.tochka.com/uapi/acquiring/v1.0/payments/' . rawurlencode($operationId),
        ['Authorization: Bearer ' . $token, 'Accept: application/json']
    );
    $j = $body ? json_decode($body, true) : null;
    $op = $j['Data']['Operation'][0] ?? ($j['Data'] ?? null);
    return is_array($op) ? (string)($op['status'] ?? '') ?: null : null;
}
