<?php
// Database credentials — override via environment variables for production.
// Example: export DB_HOST=myserver DB_NAME=mydb DB_USER=myuser DB_PASS=secret
define('DB_HOST',     getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',     getenv('DB_NAME')    ?: 'indicadores');
define('DB_USER',     getenv('DB_USER')    ?: 'root');
define('DB_PASS',     getenv('DB_PASS')    ?: '');
define('DB_CHARSET',  'utf8mb4');
define('BASE_PATH',   dirname(__DIR__));

// Detect the subfolder path automatically so the app works whether installed
// at the web-root (/) or inside a subfolder (e.g. /indicadores/).
// Override with the APP_URL env var in production (e.g. https://domain.com/indicadores/).
if (getenv('APP_URL') !== false && getenv('APP_URL') !== '') {
    define('BASE_URL', rtrim(getenv('APP_URL'), '/') . '/');
} else {
    // __DIR__ is <docroot>/indicadores/config  → two levels up gives docroot.
    $docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $basePath = rtrim(dirname(__DIR__), '/\\');
    $subDir   = str_replace($docRoot, '', $basePath);
    $subDir   = str_replace('\\', '/', $subDir); // normalise Windows paths
    define('BASE_URL', rtrim($subDir, '/') . '/');
}

define('APP_URL', BASE_URL); // kept for backward-compat
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('APP_NAME',    'Indicadores APS');

