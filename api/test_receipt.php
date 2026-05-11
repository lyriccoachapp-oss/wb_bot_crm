<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8072/api/v1/receipts/recognize?u_id=1");
curl_setopt($ch, CURLOPT_POST, 1);
$cfile = new CURLFile(realpath("/var/www/crm.workbangers.com/storage/app/public/receipt.jpg"), 'image/jpeg', 'receipt.jpg');
curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Headers for Auth (as Bot)
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Bot-Token: zyxwvutsrqponmlkjihgfedcba",
    "X-Telegram-Id: 1"
]);
$res = curl_exec($ch);
echo $res;
