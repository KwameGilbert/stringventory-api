<?php
/**
 * Generate VAPID keys for Web Push Notifications.
 */

require __DIR__ . '/vendor/autoload.php';

use Base64Url\Base64Url;

$opensslBin = 'C:\\Program Files\\Git\\mingw64\\bin\\openssl.exe';
if (!file_exists($opensslBin)) {
    $opensslBin = 'C:\\Program Files\\Git\\usr\\bin\\openssl.exe';
}

$privatePem = shell_exec('"' . $opensslBin . '" ecparam -name prime256v1 -genkey -noout 2>NUL');
if (!$privatePem) {
    echo 'Failed to run openssl binary.' . PHP_EOL;
    exit(1);
}

$privateKey = openssl_pkey_get_private($privatePem);
if (!$privateKey) {
    echo 'Failed to load generated key: ' . openssl_error_string() . PHP_EOL;
    exit(1);
}

$details    = openssl_pkey_get_details($privateKey);
$publicKey  = Base64Url::encode("\x04" . $details['ec']['x'] . $details['ec']['y']);
$privateB64 = Base64Url::encode($details['ec']['d']);

echo 'Add these to your .env file:' . PHP_EOL . PHP_EOL;
echo 'VAPID_PUBLIC_KEY=' . $publicKey . PHP_EOL;
echo 'VAPID_PRIVATE_KEY=' . $privateB64 . PHP_EOL;
echo PHP_EOL;
echo 'Share VAPID_PUBLIC_KEY with your frontend dev.' . PHP_EOL;
