<?php
// Database credentials — override via environment variables for production.
// Example: export DB_HOST=myserver DB_NAME=mydb DB_USER=myuser DB_PASS=secret
define('DB_HOST',     getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',     getenv('DB_NAME')    ?: 'indicadores');
define('DB_USER',     getenv('DB_USER')    ?: 'root');
define('DB_PASS',     getenv('DB_PASS')    ?: '');
define('DB_CHARSET',  'utf8mb4');
define('BASE_PATH',   dirname(__DIR__));
define('BASE_URL',    '/');
// Set APP_URL env var in production to e.g. https://indicadores.exemplo.gov.br
define('APP_URL',     getenv('APP_URL')    ?: '');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('APP_NAME',    'Indicadores APS');

