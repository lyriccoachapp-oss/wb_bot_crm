<?php
session_start();

require_once __DIR__ . '/includes/ApiClient.php';

$api = new ApiClient();
$route = $_GET['route'] ?? 'dashboard';

// Проверяем авторизацию
$isGuestRoute = in_array($route, ['login', 'forgot-password', 'reset-password']);

if (!$isGuestRoute && empty($_SESSION['api_token'])) {
    header('Location: /?route=login');
    exit;
}

// Если авторизован, но лезет на логин
if ($isGuestRoute && !empty($_SESSION['api_token'])) {
    header('Location: /?route=dashboard');
    exit;
}

// Простой роутер
switch ($route) {
    case 'login':
        require __DIR__ . '/templates/auth/login.php';
        break;

    case 'forgot-password':
        require __DIR__ . '/templates/auth/forgot-password.php';
        break;

    case 'reset-password':
        require __DIR__ . '/templates/auth/reset-password.php';
        break;

    
    case 'refresh-token':
		header('Content-Type: application/json');
		$refreshToken = $_SESSION['refresh_token'] ?? '';
		if (!$refreshToken) {
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'No refresh token']);
			exit;
		}
		$response = $api->post('/auth/refresh', ['refresh_token' => $refreshToken]);
		if ($response['success'] ?? false) {
			$api->setToken($response['data']['access_token'], $response['data']['refresh_token'] ?? null);
			echo json_encode(['success' => true, 'access_token' => $response['data']['access_token']]);
		} else {
			$api->clearToken();
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => $response['error'] ?? 'Refresh failed']);
		}
		exit;

    case 'logout':
        $api->post('/auth/logout');
        $api->clearToken();
        header('Location: /?route=login');
        break;

    case 'dashboard':
        require __DIR__ . '/templates/dashboard/index.php';
        break;

    case 'users':
        require __DIR__ . '/templates/dashboard/users.php';
        break;

    case 'roles':
        require __DIR__ . '/templates/dashboard/roles.php';
        break;

    case 'companies':
        require __DIR__ . '/templates/dashboard/companies.php';
        break;

    case 'settings':
        require __DIR__ . '/templates/dashboard/settings.php';
        break;

    case 'geolocation':
        require __DIR__ . '/templates/dashboard/geolocation.php';
        break;

    case 'objects':
        require __DIR__ . '/templates/dashboard/objects.php';
        break;

    case 'receipts':
        require __DIR__ . '/templates/dashboard/receipts.php';
        break;

    case 'reports':
        require __DIR__ . '/templates/dashboard/reports.php';
        break;

    case 'content':
        require __DIR__ . '/templates/dashboard/content.php';
        break;

    default:
        http_response_code(404);
        echo "404 Not Found";
        break;
}
