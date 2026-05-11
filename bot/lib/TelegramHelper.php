<?php
class TelegramHelper {
    private string $botToken;

    public function __construct(string $botToken) {
        $this->botToken = $botToken;
    }

    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): array|bool {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request('sendMessage', $data);
    }

    public function deleteMessage(int|string $chatId, int $messageId): array|bool {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        return $this->request('deleteMessage', $data);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array|bool {
        $data = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ];
        return $this->request('answerCallbackQuery', $data);
    }

    private function request(string $method, array $data): array|bool {
        $url = "https://api.telegram.org/bot{$this->botToken}/{$method}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            error_log("TelegramHelper Error: Method: {$method}, HTTP: {$httpCode}, Response: " . ($response ?: curl_error($ch)));
            return false;
        }

        return json_decode($response, true);
    }
}
