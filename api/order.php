<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['ok' => false, 'error' => 'Метод не поддерживается'], 405);

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) json_out(['ok' => false, 'error' => 'Некорректные данные'], 400);

// ловушка для ботов: скрытое поле должно быть пустым
if (!empty($in['website'])) json_out(['ok' => true, 'number' => '0', 'payPending' => false]);

// простое ограничение частоты: не чаще 1 заказа в 20 секунд с одного IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$rlFile = PRIVATE_DIR . '/ratelimit.json';
$rl = read_json($rlFile, []);
$now = time();
foreach ($rl as $k => $t) if ($now - (int)$t > 300) unset($rl[$k]);
$key = hash('sha256', $ip);
if (isset($rl[$key]) && $now - (int)$rl[$key] < 20) json_out(['ok' => false, 'error' => 'Слишком часто. Подождите немного и повторите.'], 429);
$rl[$key] = $now;
write_json($rlFile, $rl);

$clean = static function ($v, int $max = 300): string {
    $v = trim((string)$v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
};
$digits = static fn(string $s): string => preg_replace('/\D/', '', $s) ?? '';

$products = read_json(DATA_DIR . '/products.json', []);
$product = null;
foreach ($products as $p) {
    if (($p['id'] ?? '') === (string)($in['product'] ?? '') && ($p['active'] ?? true) !== false) { $product = $p; break; }
}
if (!$product) json_out(['ok' => false, 'error' => 'Букет не найден. Обновите страницу и выберите заново.'], 422);

$self = !empty($in['self']);
$o = [
    'name' => $clean($in['name'] ?? '', 80),
    'phone' => $clean($in['phone'] ?? '', 30),
    'date' => $clean($in['date'] ?? '', 10),
    'time' => $clean($in['time'] ?? '', 60),
    'address' => $clean($in['address'] ?? '', 250),
    'rname' => $clean($in['rname'] ?? '', 80),
    'rphone' => $clean($in['rphone'] ?? '', 30),
    'anon' => !empty($in['anon']),
    'card' => $clean($in['card'] ?? '', 500),
    'comment' => $clean($in['comment'] ?? '', 800),
];
if ($self) { $o['rname'] = $o['name']; $o['rphone'] = $o['phone']; }

$bad = [];
if (mb_strlen($o['name']) < 2) $bad[] = 'name';
if (strlen($digits($o['phone'])) < 11) $bad[] = 'phone';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $o['date']) || $o['date'] < date('Y-m-d', strtotime('-1 day'))) $bad[] = 'date';
if (mb_strlen($o['address']) < 5) $bad[] = 'address';
if (mb_strlen($o['rname']) < 2) $bad[] = 'rname';
if (strlen($digits($o['rphone'])) < 11) $bad[] = 'rphone';
if ($bad) json_out(['ok' => false, 'error' => 'Проверьте выделенные поля.', 'fields' => $bad], 422);

$payOnline = (($in['pay'] ?? 'online') === 'online');
$number = date('ymd') . '-' . random_int(100, 999);
$amount = (float)$product['price'];  // цена берётся с сервера, не из формы

$order = $o + [
    'number' => $number,
    'created' => date('c'),
    'product_id' => $product['id'],
    'product_name' => $product['name'],
    'amount' => $amount,
    'pay_method' => $payOnline ? 'online' : 'later',
    'status' => 'new',
    'payment_id' => '',
];

$payment = null;
if ($payOnline) {
    $payment = tochka_create_payment($amount, 'Заказ №' . $number . ' — ' . $product['name'], $number);
    if ($payment) { $order['status'] = 'awaiting_payment'; $order['payment_id'] = $payment['id']; $order['payment_url'] = $payment['url']; }
    else $order['status'] = 'pay_link_failed';
}

if (!write_json(PRIVATE_DIR . '/orders/' . $number . '.json', $order)) {
    json_out(['ok' => false, 'error' => 'Не удалось сохранить заказ. Напишите нам в мессенджер.'], 500);
}

$msg = "🌸 <b>Новый заказ №{$number}</b>\n"
    . "<b>{$order['product_name']}</b> — " . number_format($amount, 0, ',', ' ') . " ₽\n\n"
    . "👤 " . h($o['name']) . ", " . h($o['phone']) . "\n"
    . "📅 " . h($o['date']) . ($o['time'] !== '' ? ', ' . h($o['time']) : '') . "\n"
    . "📍 " . h($o['address']) . "\n"
    . "🎁 Получатель: " . h($o['rname']) . ", " . h($o['rphone']) . ($o['anon'] ? " (анонимно)" : "") . "\n"
    . ($o['card'] !== '' ? "💌 Открытка: " . h($o['card']) . "\n" : '')
    . ($o['comment'] !== '' ? "💬 " . h($o['comment']) . "\n" : '')
    . "\n💳 " . ($payOnline ? ($payment ? 'онлайн-оплата — ждём оплату' : '⚠️ онлайн-оплата: ссылка не создалась, отправьте клиенту вручную') : 'оплата по договорённости');
telegram_notify($msg);

json_out([
    'ok' => true,
    'number' => $number,
    'paymentUrl' => $payment['url'] ?? null,
    'payPending' => $payOnline && !$payment,
]);
