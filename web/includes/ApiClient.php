<?php

/**
 * Клиент для общения с центральным CRM API
 */
class ApiClient
{
    private string $baseUrl;
    private ?string $token = null;

    public function __construct(string $baseUrl = 'http://api/api/v1')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        
        // Попытка взять токен из сессии
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['api_token'])) {
            $this->token = $_SESSION['api_token'];
        }
    }

    public function setToken(string $token, ?string $refreshToken = null): void
    {
        $this->token = $token;
        $_SESSION['api_token'] = $token;
        if ($refreshToken) {
            $_SESSION['refresh_token'] = $refreshToken;
        }
    }

    public function clearToken(): void
    {
        $this->token = null;
        unset($_SESSION['api_token']);
        unset($_SESSION['refresh_token']);
        unset($_SESSION['user_info']);
    }

    /**
     * Выполнить GET запрос
     */
    public function get(string $endpoint, array $params = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $this->request('GET', $url);
    }

    /**
     * Выполнить POST запрос
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $this->baseUrl . '/' . ltrim($endpoint, '/'), $data);
    }
    
    /**
     * Выполнить PUT запрос
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $this->baseUrl . '/' . ltrim($endpoint, '/'), $data);
    }

    private function request(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        // Отключаем проверку SSL для локальной разработки, если нужно
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Таймаут для CURL: 30 секунд
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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

        // Если токен истек
        if ($httpCode === 401) {
            // Пробуем обновить токен по refresh_token (избегаем бесконечной рекурсии)
            if (!str_ends_with($url, '/auth/refresh')) {
                if ($this->tryRefresh()) {
                    // Токен успешно обновлен, повторяем исходный запрос
                    return $this->request($method, $url, $data);
                }
            }

            $this->clearToken();
            if (!headers_sent()) {
                header('Location: /?route=login');
                exit;
            }
        }

        return $decoded;
    }

    /**
     * Попытаться обновить access токен через API
     */
    private function tryRefresh(): bool
    {
        $refreshToken = $_SESSION['refresh_token'] ?? null;
        if (!$refreshToken) {
            return false;
        }

        $ch = curl_init();
        $url = $this->baseUrl . '/auth/refresh';
        
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'refresh_token' => $refreshToken
        ]));
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return false;
        }

        $decoded = json_decode($response, true);
        if ($decoded && ($decoded['success'] ?? false) && !empty($decoded['data']['access_token'])) {
            $this->setToken($decoded['data']['access_token'], $decoded['data']['refresh_token'] ?? null);
            return true;
        }

        return false;
    }
}
