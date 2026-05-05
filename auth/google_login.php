<?php
session_start();

require_once '../config/secrets.php';
$client_id    = GOOGLE_CLIENT_ID;
$redirect_uri = GOOGLE_REDIRECT_URI;

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'client_id'     => $client_id,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
]);

header("Location: https://accounts.google.com/o/oauth2/auth?" . $params);
exit();

