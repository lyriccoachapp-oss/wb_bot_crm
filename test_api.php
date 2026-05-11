<?php
require __DIR__.'/api/vendor/autoload.php';
$app = require_once __DIR__.'/api/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::where('email', 'admin@workbangers.com')->first();
$request = \Illuminate\Http\Request::create('/api/v1/time-entries', 'GET', ['page' => 1, 'limit' => 25]);
$request->setUserResolver(function() use ($user) { return $user; });
// we need to set auth_user on request since middleware usually does it
$request->merge(['auth_user' => $user]);

$controller = $app->make(\App\Http\Controllers\V1\TimeEntryController::class);
$response = $controller->index($request);
echo $response->getContent();
