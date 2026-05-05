<?php
session_start();

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

session_regenerate_id(true);

require_once '../config/secrets.php';
$app_id       = FB_APP_ID;
$redirect_uri = FB_REDIRECT_URI;

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'client_id'     => $app_id,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'email,public_profile',
    'state'         => $state,
]);

header("Location: https://www.facebook.com/v19.0/dialog/oauth?" . $params);
exit();

