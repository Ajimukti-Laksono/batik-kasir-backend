<?php
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Setup tmp storage directories for Vercel Serverless (Read-Only bypass)
// Fix Laravel stripping the /api prefix because the script is in the /api folder
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_ACCEPT'] = 'application/json';

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

// Override Cache Paths to force dynamic config resolution on Vercel
$_ENV['APP_PACKAGES_CACHE'] = '/tmp/storage/bootstrap/cache/packages.php';
$_ENV['APP_SERVICES_CACHE'] = '/tmp/storage/bootstrap/cache/services.php';
$_ENV['APP_CONFIG_CACHE'] = '/tmp/storage/bootstrap/cache/config.php';
$_ENV['APP_ROUTES_CACHE'] = '/tmp/storage/bootstrap/cache/routes.php';
$_ENV['APP_EVENTS_CACHE'] = '/tmp/storage/bootstrap/cache/events.php';
putenv('APP_PACKAGES_CACHE=' . $_ENV['APP_PACKAGES_CACHE']);
putenv('APP_SERVICES_CACHE=' . $_ENV['APP_SERVICES_CACHE']);
putenv('APP_CONFIG_CACHE=' . $_ENV['APP_CONFIG_CACHE']);
putenv('APP_ROUTES_CACHE=' . $_ENV['APP_ROUTES_CACHE']);
putenv('APP_EVENTS_CACHE=' . $_ENV['APP_EVENTS_CACHE']);

// Fix empty driver issues from Vercel UI overriding with empty strings
$defaults = [
    'LOG_CHANNEL' => 'stderr',
    'CACHE_STORE' => 'file',
    'CACHE_DRIVER' => 'file',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'BROADCAST_CONNECTION' => 'log',
    'FILESYSTEM_DISK' => 'local',
    'MAIL_MAILER' => 'log',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views'
];
foreach ($defaults as $key => $value) {
    if (empty($_ENV[$key]) && empty(getenv($key))) {
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

// Force Laravel to use Vercel's injected DATABASE_URL (from Neon integration)
// This overrides any stale DB_HOST/DB_PASSWORD env vars the user might have left behind.
$dbUrl = !empty($_ENV['DATABASE_URL']) ? $_ENV['DATABASE_URL'] : (!empty(getenv('DATABASE_URL')) ? getenv('DATABASE_URL') : null);
if ($dbUrl) {
    $_ENV['DB_URL'] = $dbUrl;
    putenv("DB_URL=" . $dbUrl);
    $_ENV['DB_CONNECTION'] = 'pgsql';
    putenv("DB_CONNECTION=pgsql");

    // Wipe stale DB vars that might take precedence or interfere
    $staleVars = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
    foreach ($staleVars as $var) {
        unset($_ENV[$var]);
        putenv("$var=");
    }
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

// Force Laravel to use the writable /tmp folder
$app->useStoragePath('/tmp/storage');

$app->handleRequest(Request::capture());
