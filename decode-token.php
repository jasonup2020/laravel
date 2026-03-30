<?php
$token = file_get_contents('storage/logs/test-token.txt');
$parts = explode('.', $token);
if (count($parts) === 3) {
    $payload = json_decode(base64_decode($parts[1]), true);
    echo "Token Payload:\n";
    print_r($payload);
} else {
    echo "Invalid token format\n";
}
