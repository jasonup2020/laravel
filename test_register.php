<?php

$baseUrl = 'http://laravel-saas-ultimate-pro-full.test.com/api/v1';

echo "用户注册测试\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/auth/register");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => 'testuser' . time(),
    'email' => 'test' . time() . '@example.com',
    'password' => 'password123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP状态码: {$httpCode}\n";
$data = json_decode($response, true);
echo "响应: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
