<?php

/**
 * API接口测试脚本
 * 
 * 测试所有API接口的增删改查操作
 */

$baseUrl = 'http://laravel-saas-ultimate-pro-full.test.com/api/v1';
$testResults = [];
$testDetails = [];

echo "\n" . str_repeat("=", 80) . "\n";
echo "API 接口测试报告\n";
echo str_repeat("=", 80) . "\n\n";

// 测试函数
function testApi($name, $method, $url, $data = null, $headers = []) {
    global $testResults, $testDetails;
    
    echo "\n=== 测试: {$name} ===\n";
    echo "请求: {$method} {$url}\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $defaultHeaders = ['Content-Type: application/json', 'Accept: application/json'];
    $allHeaders = array_merge($defaultHeaders, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $result = [
        'name' => $name,
        'method' => $method,
        'url' => $url,
        'status_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'success' => in_array($httpCode, [200, 201]),
    ];
    
    $testResults[] = $result;
    
    if ($result['success']) {
        echo "✅ PASS - 状态码: {$httpCode}\n";
        $testDetails[] = "✅ {$name}: 成功 (状态码: {$httpCode})";
    } else {
        echo "❌ FAIL - 状态码: {$httpCode}\n";
        if ($error) {
            echo "错误: {$error}\n";
        }
        $testDetails[] = "❌ {$name}: 失败 (状态码: {$httpCode})";
    }
    
    echo "响应: " . substr($response, 0, 200) . "...\n";
    
    return $result;
}

// ==================== 测试开始 ====================

// 1. 健康检查
echo "\n" . str_repeat("-", 80) . "\n";
echo "1. 健康检查测试\n";
echo str_repeat("-", 80) . "\n";
testApi('健康检查', 'GET', 'http://laravel-saas-ultimate-pro-full.test.com/api/health');

// 2. 认证测试
echo "\n" . str_repeat("-", 80) . "\n";
echo "2. 认证接口测试\n";
echo str_repeat("-", 80) . "\n";

$loginResult = testApi('用户登录', 'POST', "{$baseUrl}/auth/login", [
    'email' => 'admin@example.com',
    'password' => 'admin123'
]);

$token = null;
if ($loginResult['success']) {
    $responseData = json_decode($loginResult['response'], true);
    $token = $responseData['data']['access_token'] ?? null;
    echo "获取到Token: " . substr($token, 0, 30) . "...\n";
}

// 3. 用户管理测试
echo "\n" . str_repeat("-", 80) . "\n";
echo "3. 用户管理接口测试\n";
echo str_repeat("-", 80) . "\n";

if ($token) {
    $authHeaders = ["Authorization: Bearer {$token}"];
    
    // 获取用户列表
    testApi('获取用户列表', 'GET', "{$baseUrl}/users?page=1&per_page=10", null, $authHeaders);
    
    // 创建用户
    $createUserResult = testApi('创建用户', 'POST', "{$baseUrl}/users", [
        'username' => 'testuser' . time(),
        'name' => 'Test User',
        'email' => 'test' . time() . '@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'status' => 1
    ], $authHeaders);
    
    // 获取用户详情
    testApi('获取用户详情', 'GET', "{$baseUrl}/users/1", null, $authHeaders);
    
    // 更新用户
    testApi('更新用户', 'PUT', "{$baseUrl}/users/1", [
        'name' => 'Updated User Name'
    ], $authHeaders);
}

// 4. 租户管理测试
echo "\n" . str_repeat("-", 80) . "\n";
echo "4. 租户管理接口测试\n";
echo str_repeat("-", 80) . "\n";

if ($token) {
    // 获取租户列表
    testApi('获取租户列表', 'GET', "{$baseUrl}/tenants?page=1&per_page=10", null, $authHeaders);
    
    // 创建租户
    $createTenantResult = testApi('创建租户', 'POST', "{$baseUrl}/tenants", [
        'name' => 'Test Company ' . time(),
        'code' => 'TEST' . time(),
        'domain' => 'test' . time() . '.example.com',
        'contact_name' => 'John Doe',
        'contact_email' => 'john@example.com',
        'status' => 1
    ], $authHeaders);
    
    // 获取租户详情
    testApi('获取租户详情', 'GET', "{$baseUrl}/tenants/1", null, $authHeaders);
}

// 5. 角色管理测试
echo "\n" . str_repeat("-", 80) . "\n";
echo "5. 角色管理接口测试\n";
echo str_repeat("-", 80) . "\n";

if ($token) {
    // 获取角色列表
    testApi('获取角色列表', 'GET', "{$baseUrl}/roles", null, $authHeaders);
}

// ==================== 生成报告 ====================

echo "\n\n" . str_repeat("=", 80) . "\n";
echo "测试结果汇总\n";
echo str_repeat("=", 80) . "\n\n";

$passCount = count(array_filter($testResults, fn($r) => $r['success']));
$failCount = count($testResults) - $passCount;
$totalCount = count($testResults);

echo "总测试数: {$totalCount}\n";
echo "通过: {$passCount}\n";
echo "失败: {$failCount}\n";
echo "通过率: " . round($passCount / $totalCount * 100, 2) . "%\n\n";

echo "测试明细:\n";
echo str_repeat("-", 80) . "\n";
foreach ($testDetails as $detail) {
    echo $detail . "\n";
}

// 保存报告到文件
$reportContent = "# API 接口测试报告\n\n";
$reportContent .= "生成时间: " . date('Y-m-d H:i:s') . "\n\n";
$reportContent .= "## 测试环境\n";
$reportContent .= "- 测试域名: {$baseUrl}\n";
$reportContent .= "- 总测试数: {$totalCount}\n";
$reportContent .= "- 通过: {$passCount}\n";
$reportContent .= "- 失败: {$failCount}\n";
$reportContent .= "- 通过率: " . round($passCount / $totalCount * 100, 2) . "%\n\n";

$reportContent .= "## 测试明细\n\n";
foreach ($testResults as $result) {
    $status = $result['success'] ? '✅ PASS' : '❌ FAIL';
    $reportContent .= "### {$result['name']}\n";
    $reportContent .= "- 状态: {$status}\n";
    $reportContent .= "- 方法: {$result['method']}\n";
    $reportContent .= "- URL: {$result['url']}\n";
    $reportContent .= "- 状态码: {$result['status_code']}\n";
    if ($result['error']) {
        $reportContent .= "- 错误: {$result['error']}\n";
    }
    $reportContent .= "\n";
}

file_put_contents(__DIR__ . '/docs/MD/API测试报告.md', $reportContent);

echo "\n报告已保存到: docs/MD/API测试报告.md\n";
