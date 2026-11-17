<?php
/**
 * Security Configuration
 * 
 * This file contains security settings that can be adjusted based on environment.
 * For production, ensure all security features are enabled.
 */

// Detect environment
$httpHost = '';
if (isset($_SERVER['HTTP_HOST'])) {
    $httpHost = $_SERVER['HTTP_HOST'];
}
$hostOnly = strtolower(explode(':', $httpHost)[0]);
$isLocalhost = in_array($hostOnly, ['localhost', '127.0.0.1'], true)
    || str_ends_with($hostOnly, '.test')
    || str_ends_with($hostOnly, '.local')
    || preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)/', $hostOnly) === 1;

$appEnv = getenv('APP_ENV');
if ($appEnv !== false && $appEnv !== '') {
    $isLocalhost = ($appEnv !== 'production');
}

$isHTTPS = false;
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $isHTTPS = true;
}
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $isHTTPS = true;
}

$environment = 'production';
if ($isLocalhost) {
    $environment = 'development';
}
$config = [
    'cookie_secure' => !$isLocalhost && $isHTTPS,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'session_timeout' => 3600,
    'session_regenerate_interval' => 1800,
    'use_strict_mode' => true,
    'force_https' => !$isLocalhost,
    'hsts_enabled' => !$isLocalhost && $isHTTPS,
    'csp_enabled' => true,
    'csp_report_only' => $isLocalhost,
    'is_localhost' => $isLocalhost,
    'is_https' => $isHTTPS,
    'environment' => $environment
];
return $config;