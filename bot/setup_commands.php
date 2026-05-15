<?php
/**
 * Одноразовый скрипт для установки команд бота (кнопка Меню в Telegram).
 * Устанавливает локализованные описания для ru, uk, en.
 * Запускать один раз: php setup_commands.php
 */

define('TOKEN', '6263408125:AAGcrleG66uQKK_GWfmeoHyIPGbhJQFaJW4');

$languages = [
    ''   => [['command' => 'start', 'description' => 'Start']], // дефолт (fallback)
    'ru' => [['command' => 'start', 'description' => 'Начало работы']],
    'uk' => [['command' => 'start', 'description' => 'Початок роботи']],
    'en' => [['command' => 'start', 'description' => 'Start working']],
];

foreach ($languages as $lang => $commands) {
    $url = "https://api.telegram.org/bot" . TOKEN . "/setMyCommands";

    $payload = ['commands' => $commands];
    if ($lang !== '') {
        $payload['language_code'] = $lang;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    curl_close($ch);

    echo "[{$lang}] Response: {$response}\n";
}
