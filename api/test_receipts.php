<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$repo = app(\App\Repositories\BotReceiptRepository::class);
$paginator = $repo->paginate(['telegram_id' => 476820152], 1, 15);
echo "Count: " . count($paginator->items()) . "\n";
foreach($paginator->items() as $item) {
    echo $item->id_receipt . " - " . $item->receipt_amount . "\n";
}
