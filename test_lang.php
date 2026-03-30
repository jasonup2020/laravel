<?php

function testApi($url, $headers = [], $data = []) {
    $ch = curl_init();
    
    $curlHeaders = ['Content-Type: application/json'];
    foreach ($headers as $key => $value) {
        $curlHeaders[] = "$key: $value";
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $curlHeaders,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

$baseUrl = 'http://laravel-saas-ultimate-pro-full.test.com/api/v1';

echo "=== API 多语言测试 ===\n\n";

echo "--- 中文 (zh-CN) ---\n";
$result = testApi("$baseUrl/auth/login", ['Accept-Language' => 'zh-CN'], []);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "--- 英文 (en) ---\n";
$result = testApi("$baseUrl/auth/login", ['Accept-Language' => 'en'], []);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "--- 韩文 (ko) ---\n";
$result = testApi("$baseUrl/auth/login", ['Accept-Language' => 'ko'], []);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
