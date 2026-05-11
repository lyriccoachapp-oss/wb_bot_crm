<?php
date_default_timezone_set('America/Halifax');

require_once('../lib/ApiClient.php');
$api = new ApiClient();

header('Content-Type: application/json');

$u_id = isset($_GET['u_id']) ? (int)$_GET['u_id'] : 0;
if ($u_id <= 0) {
	http_response_code(400);
	echo json_encode(['status' => 'error', 'message' => 'Missing user identifier']);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
	exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['action'])) {
	http_response_code(400);
	echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
	exit;
}

if ($input['action'] === 'checkin') {
	$data = [
		'place_id' => isset($input['id_place']) ? (int)$input['id_place'] : 0,
		'latitude' => isset($input['latitude']) ? (float)$input['latitude'] : 0,
		'longitude' => isset($input['longitude']) ? (float)$input['longitude'] : 0
	];
	
	$res = $api->post('time-entries/check-in', $u_id, $data);
	
	if (isset($res['success']) && $res['success']) {
		echo json_encode([
			'status' => 'ok',
			'id_worktime' => $res['data']['id'],
			'id_place' => $data['place_id'],
			'checkin_time' => $res['data']['checkin']
		]);
	} else {
		echo json_encode(['status' => 'error', 'message' => $res['error'] ?? 'Check-in error']);
	}
	exit;
}

if ($input['action'] === 'checkout') {
	$data = [
		'work_desc' => isset($input['comment']) ? $input['comment'] : '',
		'latitude' => isset($input['latitude']) ? (float)$input['latitude'] : 0,
		'longitude' => isset($input['longitude']) ? (float)$input['longitude'] : 0
	];
	
	$res = $api->post('time-entries/check-out', $u_id, $data);
	
	if (isset($res['success']) && $res['success']) {
		echo json_encode(['status' => 'ok']);
	} else {
		echo json_encode(['status' => 'error', 'message' => $res['error'] ?? 'Checkout error']);
	}
	exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
