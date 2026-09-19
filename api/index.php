<?php
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Setup tmp storage directories for Vercel Serverless (Read-Only bypass)
$directories = [
    '/tmp/storage/app',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/testing',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs'
];
foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

// Force Laravel to use the writable /tmp folder
$app->useStoragePath('/tmp/storage');

$app->handleRequest(Request::capture());
