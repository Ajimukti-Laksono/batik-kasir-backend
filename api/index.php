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
    '/tmp/storage/logs',
    '/tmp/storage/bootstrap/cache'
];
foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }
}

// Override Cache Paths
$_ENV['APP_PACKAGES_CACHE'] = '/tmp/storage/bootstrap/cache/packages.php';
$_ENV['APP_SERVICES_CACHE'] = '/tmp/storage/bootstrap/cache/services.php';
putenv('APP_PACKAGES_CACHE=' . $_ENV['APP_PACKAGES_CACHE']);
putenv('APP_SERVICES_CACHE=' . $_ENV['APP_SERVICES_CACHE']);

// Fix empty driver issues from Vercel UI overriding with empty strings
$defaults = [
    'LOG_CHANNEL' => 'stderr',
    'CACHE_STORE' => 'file',
    'CACHE_DRIVER' => 'file',
    'SESSION_DRIVER' => 'array',
    'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views'
];
foreach ($defaults as $key => $value) {
    if (empty($_ENV[$key]) && empty(getenv($key))) {
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

// Force Laravel to use the writable /tmp folder
$app->useStoragePath('/tmp/storage');

$app->handleRequest(Request::capture());
