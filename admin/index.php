<?php
declare(strict_types=1);
require __DIR__ . '/../api/lib.php';

session_name('tsveti_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
session_start();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$adminFile = PRIVATE_DIR . '/admin.json';
$admin = read_json($adminFile, []);
$hasPass = !empty($admin['hash']);
$error = '';

if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: index.php'); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !isset($_SESSION['auth'])) {
    $pw = (string)($_POST['password'] ?? '');
    if (!$hasPass) {
        // первичная установка пароля
        if (mb_strlen($pw) < 8) $error = 'Пароль должен быть не короче 8 символов.';
        elseif ($pw !== (string)($_POST['password2'] ?? '')) $error = 'Пароли не совпадают.';
        else {
            write_json($adminFile, ['hash' => password_hash($pw, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['auth'] = true;
            header('Location: index.php'); exit;
        }
    } else {
        // ограничение подбора: 8 попыток за 10 минут с одного IP
        $lf = PRIVATE_DIR . '/login_attempts.json';
        $la = read_json($lf, []);
        $k = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '0');
        $la[$k] = array_values(array_filter($la[$k] ?? [], fn($t) => time() - (int)$t < 600));
        if (count($la[$k]) >= 8) {
            $error = 'Слишком много попыток. Подождите 10 минут.';
        } elseif (password_verify($pw, (string)$admin['hash'])) {
            unset($la[$k]); write_json($lf, $la);
            session_regenerate_id(true);
            $_SESSION['auth'] = true;
            header('Location: index.php'); exit;
        } else {
            $la[$k][] = time(); write_json($lf, $la);
            sleep(1);
            $error = 'Неверный пароль.';
        }
    }
}

$auth = !empty($_SESSION['auth']);
if ($auth && empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Управление — Цвети</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body class="adm">
<?php if (!$auth): ?>
  <main class="login">
    <form method="post" class="login__box">
      <div class="logo">Цвети</div>
      <h1><?= $hasPass ? 'Вход в управление' : 'Придумайте пароль' ?></h1>
      <?php if (!$hasPass): ?><p class="muted">Это первый вход. Задайте пароль для управления сайтом (не короче 8 символов).</p><?php endif; ?>
      <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
      <div class="f"><label for="password">Пароль</label><input id="password" name="password" type="password" autocomplete="<?= $hasPass ? 'current-password' : 'new-password' ?>" required autofocus></div>
      <?php if (!$hasPass): ?><div class="f"><label for="password2">Повторите пароль</label><input id="password2" name="password2" type="password" autocomplete="new-password" required></div><?php endif; ?>
      <button class="btn btn--primary btn--wide" type="submit"><?= $hasPass ? 'Войти' : 'Сохранить и войти' ?></button>
    </form>
  </main>
<?php else: ?>
  <header class="adm__top">
    <div class="wrap adm__in">
      <span class="logo logo--sm">Цвети · управление</span>
      <nav class="tabs" id="tabs">
        <button data-tab="products" class="on">Товары</button>
        <button data-tab="orders">Заказы</button>
        <button data-tab="settings">Контакты</button>
        <button data-tab="account">Пароль</button>
      </nav>
      <span class="adm__links"><a href="../" target="_blank">Сайт ↗</a> <a href="?logout=1">Выйти</a></span>
    </div>
  </header>
  <main class="wrap adm__main" id="app" data-csrf="<?= h($_SESSION['csrf']) ?>"></main>
  <div class="toast" id="toast" hidden></div>
  <script src="../assets/admin.js"></script>
<?php endif; ?>
</body>
</html>
