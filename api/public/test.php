<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$user = \App\Models\BotUser::first();
if (!$user) { echo "No user"; exit; }

$token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);

$ch = curl_init('http://localhost/api/v1/time-entries/check-in');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['place_id' => 1, 'latitude' => 50, 'longitude' => 30]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token,
    'Host: crm.workbangers.com'
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP $code\n";
echo "Response: $res\n";
