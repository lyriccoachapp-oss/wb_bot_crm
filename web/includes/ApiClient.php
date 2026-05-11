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

    public function setToken(string $token): void
    {
        $this->token = $token;
        $_SESSION['api_token'] = $token;
    }

    public function clearToken(): void
    {
        $this->token = null;
        unset($_SESSION['api_token']);
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
            $this->clearToken();
            if (!headers_sent()) {
                header('Location: /?route=login');
                exit;
            }
        }

        return $decoded;
    }
}
