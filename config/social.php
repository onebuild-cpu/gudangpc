<?php
/**
 * Social OAuth Configuration
 *
 * Placeholders for Google and Facebook OAuth 2.0 credentials.
 * 
 * FOR PRODUCTION:
 *   - Google:  redirect_uri = https://gudangpc.com/auth/google/callback
 *   - Facebook: redirect_uri = https://gudangpc.com/auth/facebook/callback
 *   - Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, FACEBOOK_CLIENT_ID, FACEBOOK_CLIENT_SECRET in .env
 *
 * FOR LOCAL XAMPP TESTING:
 *   The redirect URI depends on your Google/Facebook console settings.
 *   You may need to use http://localhost/gudangpc/auth/google/callback
 *   instead of the production https:// URI, depending on which origins
 *   are authorized in your developer console.
 * 
 * The `redirect_uri` values below are shown for production;
 * override per-provider in your auth controller as needed.
 */

$config = [];

$config['google'] = [
    'client_id' => '',
    'client_secret' => '',
    'redirect_uri' => 'https://gudangpc.com/auth/google/callback',
];
if (getenv('GOOGLE_CLIENT_ID') !== '') {
    $config['google']['client_id'] = getenv('GOOGLE_CLIENT_ID');
}
if (getenv('GOOGLE_CLIENT_SECRET') !== '') {
    $config['google']['client_secret'] = getenv('GOOGLE_CLIENT_SECRET');
}

$config['facebook'] = [
    'client_id' => '',
    'client_secret' => '',
    'redirect_uri' => 'https://gudangpc.com/auth/facebook/callback',
];
if (getenv('FACEBOOK_CLIENT_ID') !== '') {
    $config['facebook']['client_id'] = getenv('FACEBOOK_CLIENT_ID');
}
if (getenv('FACEBOOK_CLIENT_SECRET') !== '') {
    $config['facebook']['client_secret'] = getenv('FACEBOOK_CLIENT_SECRET');
}

return $config;