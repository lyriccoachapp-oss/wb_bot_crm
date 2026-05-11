<?php

/**
 * Клиент для общения Telegram-бота с CRM API
 *
 * Передаёт X-Bot-Token и X-Telegram-Id для прозрачной авторизации,
 * исключая необходимость использовать базу данных напрямую.
 */
class ApiClient
{
    private $baseUrl;
    private $botToken;

    public function __construct($baseUrl = 'https://crm.workbangers.com/api/v1')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        // Используем токен, определённый в tob.php (TOKEN). Fallback - пустота (приведет к 401).
        $this->botToken = defined('TOKEN') ? TOKEN : '';
    }

    /**
     * Выполнить GET запрос
     */
    public function get($endpoint, $telegramId, $params = [])
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $this->request('GET', $url, $telegramId);
    }

    /**
     * Выполнить POST запрос
     */
    public function post($endpoint, $telegramId, $data = [])
    {
        return $this->request('POST', $this->baseUrl . '/' . ltrim($endpoint, '/'), $telegramId, $data);
    }
    
    /**
     * Выполнить PUT запрос
     */
    public function put($endpoint, $telegramId, $data = [])
    {
        return $this->request('PUT', $this->baseUrl . '/' . ltrim($endpoint, '/'), $telegramId, $data);
    }

    /**
     * Базовый метод запроса
     */
    private function request($method, $url, $telegramId, $data = [])
    {
        $ch = curl_init();

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Bot-Token: ' . $this->botToken,
            'X-Telegram-Id: ' . $telegramId
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'error' => "cURL Error: $error",
                'status' => 500
            ];
        }

        curl_close($ch);

        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON from API',
                'raw' => $response,
                'status' => $httpCode
            ];
        }
        
        $decoded['status'] = $httpCode;

        return $decoded;
    }
}
