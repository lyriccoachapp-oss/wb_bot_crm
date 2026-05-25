<?php
/**
 * Новый чистый Telegram Bot (Webhook Entry Point)
 */

define('TOKEN', '6263408125:AAGcrleG66uQKK_GWfmeoHyIPGbhJQFaJW4');
define('WEBAPP_URL_WT', 'https://crm.workbangers.com/bot/webapp/wt.php');
define('WEBAPP_URL_RECEIPTS', 'https://crm.workbangers.com/bot/webapp/wt_receipt.php');
define('WEBAPP_URL_HISTORY', 'https://crm.workbangers.com/bot/webapp/wt_history.php');
define('WEBAPP_URL_OBJECTS', 'https://crm.workbangers.com/bot/webapp/wt_objects.php');
error_reporting(E_ALL);
ini_set('display_errors', false);
ini_set('log_errors', true);
ini_set('error_log', __DIR__ . '/error.log');

require_once __DIR__ . '/lib/TelegramHelper.php';
require_once __DIR__ . '/lib/ApiClient.php';
require_once __DIR__ . '/lib/i18n.php';

$telegram = new TelegramHelper(TOKEN);
$api = new ApiClient();

$input = file_get_contents('php://input');
if (!$input) {
    echo "This is WorkBangers Bot Webhook endpoint.";
    exit;
}

$update = json_decode($input, true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    
    // Определяем язык (по умолчанию en, если есть в Telegram - берем его код)
    $lang = $message['from']['language_code'] ?? 'en';
    if (in_array(substr($lang, 0, 2), ['ru', 'uk', 'en'])) {
        $lang = substr($lang, 0, 2);
    } else {
        $lang = 'en';
    }
    
    $firstName = $message['from']['first_name'] ?? 'Employee';
    
    // Проверим, зарегистрирован ли пользователь в CRM
    $resAuth = $api->get('auth/me', $chatId);
    
    if (!isset($resAuth['success']) || !$resAuth['success']) {
        I18n::load($lang); // Загружаем язык для неавторизованного
        $telegram->sendMessage($chatId, __('bot.unregistered'));
        exit;
    }

    // Если CRM возвращает предпочитаемый язык пользователя
    if (!empty($resAuth['data']['lcode'])) {
        $lang = $resAuth['data']['lcode'];
    }
    I18n::load($lang);

    if ($text === '/start') {
		// Передаем язык и параметр версии (anti-cache) в WebApp через GET
		$webAppWt = WEBAPP_URL_WT . '?u_id=' . $chatId . '&lang=' . $lang . '&v=1.0.30';
		$webAppReceipts = WEBAPP_URL_RECEIPTS . '?u_id=' . $chatId . '&lang=' . $lang . '&v=1.0.30';
		$webAppHistory = WEBAPP_URL_HISTORY . '?u_id=' . $chatId . '&lang=' . $lang . '&v=1.0.30';
		$webAppObjects = WEBAPP_URL_OBJECTS . '?u_id=' . $chatId . '&lang=' . $lang . '&v=1.0.30';
        
        $role = $resAuth['data']['role'] ?? 'user';

        $btnApp = ['text' => __('menu.open_app'), 'web_app' => ['url' => $webAppWt], 'style' => 'primary'];
        $btnAdmin = ['text' => __('menu.admin_panel'), 'url' => 'https://crm.workbangers.com/'];
        
        $keyboardLayout = [];

        if ($role === 'admin') {
            $keyboardLayout[] = [$btnApp];
            $keyboardLayout[] = [$btnAdmin];
        } else {
            $keyboardLayout[] = [$btnApp];
        }

        $keyboard = [
            'inline_keyboard' => $keyboardLayout
        ];

        $telegram->sendMessage(
            $chatId, 
            __('bot.menu_greeting', $firstName), 
            $keyboard
        );
        exit;
    }
    
    // Ответ на любые другие текстовые сообщения
    $telegram->sendMessage($chatId, __('bot.use_menu'));
}

// Для любых коллбэков пока отдаем пустой ответ
if (isset($update['callback_query'])) {
    $telegram->answerCallbackQuery($update['callback_query']['id']);
    exit;
}
