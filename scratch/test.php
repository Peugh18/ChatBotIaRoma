<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$msg = \App\Models\Message::whereNotNull('metadata')
    ->where('metadata', 'like', '%image_url%')
    ->orderBy('id', 'desc')
    ->first();

if(!$msg) {
    echo "No message found\n";
    exit;
}

$url = $msg->metadata['image_url'] ?? null;
if(!$url) {
    echo "No image_url\n";
    exit;
}

echo "URL: " . $url . "\n";
$token = config('services.roma.token');
echo "TOKEN: " . $token . "\n";

$res = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'Bearer ' . $token,
    'X-Roma-Sync-Token' => $token
])->get($url);

echo "Status: " . $res->status() . "\n";
echo "Body: " . substr($res->body(), 0, 500) . "\n";
