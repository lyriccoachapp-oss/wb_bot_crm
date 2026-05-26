<?php
// Скрипт копирования данных пользователя с старого Telegram ID на новый Telegram ID
// Использование: php bot/copy_user.php <old_id_or_email> <new_id>

if ($argc < 3) {
	echo "Использование: php bot/copy_user.php <old_id_or_email> <new_id>\n";
	exit(1);
}

$oldParam = $argv[1];
$newId = (int)$argv[2];

// Читаем настройки БД из api/.env
$envPath = __DIR__ . '/../api/.env';
if (!file_exists($envPath)) {
	echo "Ошибка: Файл .env не найден в {$envPath}\n";
	exit(1);
}

$env = parse_ini_file($envPath);
if (!$env) {
	// Если parse_ini_file не сработал из-за нестандартного синтаксиса, распарсим вручную
	$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$env = [];
	foreach ($lines as $line) {
		if (strpos(trim($line), '#') === 0) continue;
		list($key, $value) = explode('=', $line, 2) + [NULL, NULL];
		if ($key !== NULL) {
			$env[trim($key)] = trim($value, "\"' ");
		}
	}
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
// Если скрипт запускается извне контейнера на хосте, а DB_HOST указан как host.docker.internal, заменим его на 127.0.0.1
if ($host === 'host.docker.internal') {
	$host = '127.0.0.1';
}
$db = $env['DB_DATABASE'] ?? 'workbangers_bot';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
$port = $env['DB_PORT'] ?? '3306';

try {
	$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
	]);
} catch (PDOException $e) {
	echo "Ошибка подключения к БД: " . $e->getMessage() . "\n";
	exit(1);
}

// Ищем старого пользователя в базе по email, telegram id или username
if (filter_var($oldParam, FILTER_VALIDATE_EMAIL)) {
	$stmt = $pdo->prepare("SELECT * FROM bot_users WHERE email = ? LIMIT 1");
} elseif (is_numeric($oldParam)) {
	$stmt = $pdo->prepare("SELECT * FROM bot_users WHERE id_telegram = ? LIMIT 1");
} else {
	$stmt = $pdo->prepare("SELECT * FROM bot_users WHERE username = ? LIMIT 1");
}
$stmt->execute([$oldParam]);
$oldUser = $stmt->fetch();

if (!$oldUser) {
	echo "Ошибка: Старый пользователь '{$oldParam}' не найден в таблице bot_users.\n";
	exit(1);
}

// Проверяем, существует ли уже новый Telegram ID в базе
$stmt = $pdo->prepare("SELECT id FROM bot_users WHERE id_telegram = ? LIMIT 1");
$stmt->execute([$newId]);
if ($stmt->fetch()) {
	echo "Ошибка: Пользователь с новым Telegram ID {$newId} уже существует.\n";
	exit(1);
}

// Подготавливаем новые данные на основе старой строки
$userData = $oldUser;
unset($userData['id']); // Удаляем автоинкрементный первичный ключ
$userData['id_telegram'] = $newId;
$userData['id_chat'] = $newId;
$userData['status'] = 'registred';
$userData['active'] = 1;
$userData['date_add'] = date('Y-m-d H:i:s');
$userData['date_upd'] = date('Y-m-d H:i:s');

// Формируем SQL запрос динамически
$columns = array_keys($userData);
$placeholders = array_fill(0, count($columns), '?');
$sql = "INSERT INTO bot_users (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

try {
	$stmt = $pdo->prepare($sql);
	$stmt->execute(array_values($userData));
	echo "Успешно! Данные пользователя '{$oldUser['firstname']} {$oldUser['lastname']}' скопированы со старого ID {$oldUser['id_telegram']} на новый ID {$newId}.\n";
} catch (PDOException $e) {
	echo "Ошибка сохранения нового пользователя: " . $e->getMessage() . "\n";
	exit(1);
}
