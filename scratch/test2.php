<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$msg = \App\Models\Message::whereNotNull('metadata')->where('metadata', 'like', '%lookaside%')->orderBy('id', 'desc')->first();
echo json_encode($msg->metadata, JSON_PRETTY_PRINT);
