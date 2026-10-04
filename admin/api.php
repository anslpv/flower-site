<?php
declare(strict_types=1);
require __DIR__ . '/../api/lib.php';

session_name('tsveti_admin');
session_start();
header('X-Robots-Tag: noindex');

if (empty($_SESSION['auth'])) json_out(['ok' => false, 'error' => 'Нужно войти заново'], 401);
$csrf = $_SERVER['HTTP_X_CSRF'] ?? '';
if (!is_string($csrf) || !hash_equals((string)($_SESSION['csrf'] ?? ''), $csrf)) json_out(['ok' => false, 'error' => 'Сессия устарела, обновите страницу'], 403);

$action = (string)($_GET['action'] ?? '');
$body = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
}

$str = static function ($v, int $max): string {
    $v = trim((string)$v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
};

switch ($action) {
    case 'load':
        $orders = [];
        foreach (glob(PRIVATE_DIR . '/orders/*.json') ?: [] as $f) { $o = read_json($f); if ($o) { unset($o['payment_url']); $orders[] = $o; } }
        usort($orders, fn($a, $b) => strcmp((string)($b['created'] ?? ''), (string)($a['created'] ?? '')));
        json_out([
            'ok' => true,
            'products' => read_json(DATA_DIR . '/products.json', []),
            'settings' => read_json(DATA_DIR . '/settings.json', []),
            'orders' => array_slice($orders, 0, 200),
            'tochka' => !empty(cfg()['tochka_token']),
            'telegram' => !empty(cfg()['telegram_bot_token']),
        ]);

    case 'save_products':
        $in = $body['products'] ?? null;
        if (!is_array($in)) json_out(['ok' => false, 'error' => 'Нет данных'], 400);
        $out = []; $ids = [];
        foreach ($in as $p) {
            if (!is_array($p)) continue;
            $name = $str($p['name'] ?? '', 120);
            if ($name === '') json_out(['ok' => false, 'error' => 'У каждого товара должно быть название'], 422);
            $id = preg_replace('/[^a-z0-9_-]/i', '', (string)($p['id'] ?? '')) ?: '';
            if ($id === '' || isset($ids[$id])) $id = 'p' . bin2hex(random_bytes(4));
            $ids[$id] = true;
            $image = (string)($p['image'] ?? '');
            // разрешаем только файлы из uploads/
            if (!preg_match('#^uploads/[A-Za-z0-9._-]+$#', $image)) $image = '';
            $price = (int)round((float)($p['price'] ?? 0));
            if ($price < 1 || $price > 1000000) json_out(['ok' => false, 'error' => "Проверьте цену у «{$name}»"], 422);
            $out[] = ['id' => $id, 'name' => $name, 'price' => $price, 'desc' => $str($p['desc'] ?? '', 300), 'image' => $image, 'active' => ($p['active'] ?? true) !== false];
        }
        if (!write_json(DATA_DIR . '/products.json', $out)) json_out(['ok' => false, 'error' => 'Не удалось записать файл. Проверьте права на папку data/'], 500);
        json_out(['ok' => true, 'products' => $out]);

    case 'save_settings':
        $s = $body['settings'] ?? [];
        $out = [
            'phone' => $str($s['phone'] ?? '', 40),
            'whatsapp' => preg_replace('/\D/', '', (string)($s['whatsapp'] ?? '')) ?: '',
            'telegram' => ltrim($str($s['telegram'] ?? '', 80), '@'),
            'max' => $str($s['max'] ?? '', 200),
            'hours' => $str($s['hours'] ?? '', 80),
            'city' => 'Ростов-на-Дону',
        ];
        if ($out['max'] !== '' && !preg_match('#^https?://#i', $out['max'])) json_out(['ok' => false, 'error' => 'Ссылка на Max должна начинаться с https://'], 422);
        if (!write_json(DATA_DIR . '/settings.json', $out)) json_out(['ok' => false, 'error' => 'Не удалось записать файл. Проверьте права на папку data/'], 500);
        json_out(['ok' => true]);

    case 'upload':
        $f = $_FILES['photo'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) json_out(['ok' => false, 'error' => 'Файл не загрузился (возможно, слишком большой)'], 400);
        if ($f['size'] > 12 * 1024 * 1024) json_out(['ok' => false, 'error' => 'Файл больше 12 МБ'], 413);
        $info = @getimagesize($f['tmp_name']);
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($types[$info[2]])) json_out(['ok' => false, 'error' => 'Подходят только JPG, PNG или WEBP'], 415);
        $ext = $types[$info[2]];
        if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
        $name = 'p_' . date('ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest = UPLOAD_DIR . '/' . $name;
        if (!resize_image($f['tmp_name'], $dest, $info[2], 1400) && !move_uploaded_file($f['tmp_name'], $dest)) {
            json_out(['ok' => false, 'error' => 'Не удалось сохранить файл. Проверьте права на папку uploads/'], 500);
        }
        json_out(['ok' => true, 'image' => 'uploads/' . $name]);

    case 'order_status':
        $num = preg_replace('/[^0-9-]/', '', (string)($body['number'] ?? ''));
        $status = (string)($body['status'] ?? '');
        if (!in_array($status, ['new', 'paid', 'done', 'cancelled'], true)) json_out(['ok' => false, 'error' => 'Неизвестный статус'], 422);
        $file = PRIVATE_DIR . '/orders/' . $num . '.json';
        $o = read_json($file);
        if (!$o) json_out(['ok' => false, 'error' => 'Заказ не найден'], 404);
        $o['status'] = $status;
        write_json($file, $o);
        json_out(['ok' => true]);

    case 'check_payment':
        $num = preg_replace('/[^0-9-]/', '', (string)($body['number'] ?? ''));
        $file = PRIVATE_DIR . '/orders/' . $num . '.json';
        $o = read_json($file);
        if (!$o || empty($o['payment_id'])) json_out(['ok' => false, 'error' => 'У заказа нет онлайн-платежа'], 404);
        $st = tochka_payment_status((string)$o['payment_id']);
        if ($st === null) json_out(['ok' => false, 'error' => 'Не удалось получить статус. Проверьте оплату в личном кабинете Точки.'], 502);
        if (in_array(strtoupper($st), ['APPROVED', 'PAID'], true) && ($o['status'] ?? '') !== 'done') { $o['status'] = 'paid'; write_json($file, $o); }
        json_out(['ok' => true, 'status' => $st, 'order_status' => $o['status']]);

    case 'change_password':
        $new = (string)($body['password'] ?? '');
        $adminFile = PRIVATE_DIR . '/admin.json';
        $a = read_json($adminFile, []);
        if (empty($a['hash']) || !password_verify((string)($body['current'] ?? ''), (string)$a['hash'])) json_out(['ok' => false, 'error' => 'Текущий пароль указан неверно'], 422);
        if (mb_strlen($new) < 8) json_out(['ok' => false, 'error' => 'Новый пароль — не короче 8 символов'], 422);
        write_json($adminFile, ['hash' => password_hash($new, PASSWORD_DEFAULT)]);
        json_out(['ok' => true]);

    default:
        json_out(['ok' => false, 'error' => 'Неизвестное действие'], 400);
}

/** Уменьшает фото до $max px по длинной стороне (нужна расширение GD). Возвращает true, если файл сохранён. */
function resize_image(string $src, string $dest, int $type, int $max): bool
{
    if (!function_exists('imagecreatetruecolor')) return false;
    $im = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default => false,
    };
    if (!$im) return false;
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($src);
        $rot = [3 => 180, 6 => -90, 8 => 90][$ex['Orientation'] ?? 0] ?? 0;
        if ($rot) { $r = imagerotate($im, $rot, 0); if ($r) $im = $r; }
    }
    $w = imagesx($im); $h = imagesy($im);
    $k = min(1, $max / max($w, $h));
    if ($k < 1) {
        $nw = (int)round($w * $k); $nh = (int)round($h * $k);
        $n = imagecreatetruecolor($nw, $nh);
        if ($type !== IMAGETYPE_JPEG) { imagealphablending($n, false); imagesavealpha($n, true); }
        imagecopyresampled($n, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $im = $n;
    }
    return match ($type) {
        IMAGETYPE_JPEG => imagejpeg($im, $dest, 86),
        IMAGETYPE_PNG => imagepng($im, $dest, 7),
        IMAGETYPE_WEBP => imagewebp($im, $dest, 86),
        default => false,
    };
}
