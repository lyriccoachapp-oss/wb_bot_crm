<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\BotUser::where('id_telegram', 476820152)->first();
$repo = app(\App\Repositories\BotReceiptRepository::class);

$filters = ['telegram_id' => $user->id_telegram];
$paginator = $repo->paginate($filters, 1, 15);
echo "total: " . $paginator->total() . "\n";
