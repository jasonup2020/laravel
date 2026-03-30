<?php

$baseUrl = 'http://laravel-saas-ultimate-pro-full.test.com/api/v1';

// 1. 登录获取token
echo "1. 登录测试\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'admin@example.com',
    'password' => 'admin123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$loginData = json_decode($response, true);
echo "登录响应: " . json_encode($loginData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (!isset($loginData['data']['access_token'])) {
    echo "登录失败，无法继续测试\n";
    exit;
}

$accessToken = $loginData['data']['access_token'];
$refreshToken = $loginData['data']['refresh_token'];

echo "Access Token: " . substr($accessToken, 0, 50) . "...\n";
echo "Refresh Token: " . substr($refreshToken, 0, 50) . "...\n\n";

// 2. 刷新Token测试
echo "2. 刷新Token测试\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/auth/refresh");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'refresh_token' => $refreshToken
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP状态码: {$httpCode}\n";
$refreshData = json_decode($response, true);
echo "刷新响应: " . json_encode($refreshData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
