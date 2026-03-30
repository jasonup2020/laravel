<?php

/**
 * API安全测试脚本
 * 测试SQL注入和XSS攻击防护
 */

$baseUrl = 'http://laravel-saas-ultimate-pro-full.test.com';
$results = [
    'sql_injection' => [],
    'xss' => [],
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
];

// SQL注入测试载荷
$sqlInjectionPayloads = [
    "' OR '1'='1",
    "' OR '1'='1' --",
    "' OR '1'='1' /*",
    "1' OR '1'='1",
    "admin'--",
    "admin' #",
    "' UNION SELECT NULL--",
    "' UNION SELECT NULL, NULL--",
    "1; DROP TABLE users--",
    "' OR 1=1--",
];

// XSS测试载荷
$xssPayloads = [
    "<script>alert('XSS')</script>",
    "<img src=x onerror=alert('XSS')>",
    "<svg onload=alert('XSS')>",
    "javascript:alert('XSS')",
    "<body onload=alert('XSS')>",
    "<iframe src='javascript:alert(\"XSS\")'>",
    "'\"><script>alert('XSS')</script>",
    "<script>document.location='http://evil.com'</script>",
];

/**
 * 发送HTTP请求
 */
function sendRequest($url, $method, $data = [], $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $defaultHeaders = ['Content-Type: application/json', 'Accept: application/json'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response,
    ];
}

/**
 * 测试SQL注入
 */
function testSqlInjection($baseUrl, $payloads) {
    $results = [];
    
    echo "\n=== SQL注入测试 ===\n\n";
    
    // 1. 登录接口SQL注入测试
    echo "【登录接口】\n";
    foreach ($payloads as $payload) {
        $response = sendRequest("$baseUrl/api/v1/auth/login", 'POST', [
            'email' => $payload,
            'password' => 'password',
        ]);
        
        $passed = !isset($response['body']['data']['access_token']);
        $status = $passed ? '✅' : '❌';
        $results[] = [
            'endpoint' => 'login',
            'payload' => $payload,
            'passed' => $passed,
            'http_code' => $response['code'],
        ];
        echo "$status Email: " . substr($payload, 0, 30) . " - HTTP {$response['code']}\n";
    }
    
    // 2. 用户创建接口SQL注入测试
    echo "\n【用户创建接口】\n";
    // 先登录获取token
    $loginResponse = sendRequest("$baseUrl/api/v1/auth/login", 'POST', [
        'email' => 'admin@example.com',
        'password' => 'admin123',
    ]);
    
    $token = $loginResponse['body']['data']['access_token'] ?? null;
    
    if ($token) {
        foreach ($payloads as $payload) {
            $response = sendRequest("$baseUrl/api/v1/users", 'POST', [
                'name' => $payload,
                'email' => 'test' . time() . '@example.com',
                'password' => 'password123',
            ], ["Authorization: Bearer $token"]);
            
            // 检查是否返回了SQL错误
            $hasSqlError = strpos($response['raw'], 'SQL') !== false || 
                          strpos($response['raw'], 'mysql') !== false ||
                          $response['code'] === 500;
            
            $passed = !$hasSqlError;
            $status = $passed ? '✅' : '❌';
            $results[] = [
                'endpoint' => 'users.store',
                'payload' => $payload,
                'passed' => $passed,
                'http_code' => $response['code'],
            ];
            echo "$status Name: " . substr($payload, 0, 30) . " - HTTP {$response['code']}\n";
        }
    }
    
    // 3. 角色创建接口SQL注入测试
    echo "\n【角色创建接口】\n";
    if ($token) {
        foreach ($payloads as $payload) {
            $response = sendRequest("$baseUrl/api/v1/roles", 'POST', [
                'name' => $payload,
                'code' => 'test' . time(),
            ], ["Authorization: Bearer $token"]);
            
            $hasSqlError = strpos($response['raw'], 'SQL') !== false || 
                          strpos($response['raw'], 'mysql') !== false ||
                          $response['code'] === 500;
            
            $passed = !$hasSqlError;
            $status = $passed ? '✅' : '❌';
            $results[] = [
                'endpoint' => 'roles.store',
                'payload' => $payload,
                'passed' => $passed,
                'http_code' => $response['code'],
            ];
            echo "$status Name: " . substr($payload, 0, 30) . " - HTTP {$response['code']}\n";
        }
    }
    
    return $results;
}

/**
 * 测试XSS
 */
function testXss($baseUrl, $payloads) {
    $results = [];
    
    echo "\n=== XSS测试 ===\n\n";
    
    // 先登录获取token
    $loginResponse = sendRequest("$baseUrl/api/v1/auth/login", 'POST', [
        'email' => 'admin@example.com',
        'password' => 'admin123',
    ]);
    
    $token = $loginResponse['body']['data']['access_token'] ?? null;
    
    if (!$token) {
        echo "❌ 无法获取token，跳过XSS测试\n";
        return $results;
    }
    
    // 1. 用户创建接口XSS测试
    echo "【用户创建接口】\n";
    foreach ($payloads as $payload) {
        $response = sendRequest("$baseUrl/api/v1/users", 'POST', [
            'name' => $payload,
            'email' => 'xss' . time() . '@example.com',
            'password' => 'password123',
        ], ["Authorization: Bearer $token"]);
        
        // 检查XSS载荷是否被转义
        $hasXss = false;
        if (isset($response['body']['data']['name'])) {
            $hasXss = $response['body']['data']['name'] === $payload;
        }
        
        $passed = !$hasXss || $response['code'] === 422;
        $status = $passed ? '✅' : '⚠️';
        $results[] = [
            'endpoint' => 'users.store',
            'payload' => $payload,
            'passed' => $passed,
            'http_code' => $response['code'],
            'escaped' => !$hasXss,
        ];
        echo "$status Name: " . substr($payload, 0, 30) . " - HTTP {$response['code']}\n";
    }
    
    // 2. 角色创建接口XSS测试
    echo "\n【角色创建接口】\n";
    foreach ($payloads as $payload) {
        $response = sendRequest("$baseUrl/api/v1/roles", 'POST', [
            'name' => $payload,
            'code' => 'xss' . time(),
        ], ["Authorization: Bearer $token"]);
        
        $hasXss = false;
        if (isset($response['body']['data']['name'])) {
            $hasXss = $response['body']['data']['name'] === $payload;
        }
        
        $passed = !$hasXss || $response['code'] === 422;
        $status = $passed ? '✅' : '⚠️';
        $results[] = [
            'endpoint' => 'roles.store',
            'payload' => $payload,
            'passed' => $passed,
            'http_code' => $response['code'],
            'escaped' => !$hasXss,
        ];
        echo "$status Name: " . substr($payload, 0, 30) . " - HTTP {$response['code']}\n";
    }
    
    // 3. 部门创建接口XSS测试
    echo "\n【部门创建接口】\n";
    foreach ($payloads as $payload) {
        $response = sendRequest("$baseUrl/api/v1/departments", 'POST', [
            'name' => $payload,
            'code' => 'xss' . time(),
        ], ["Authorization: Bearer $token"]);
        
        $hasXss = false;
        if (isset($response['body']['data']['name'])) {
            $hasXss = $response['body']['data']['name'] === $payload;
        }
        
        $passed = !$hasXss || $response['code'] === 422;
        $status = $passed ? '✅' : '⚠️';
        $results[] = [
            'endpoint' => 'departments.store',
            'payload' => $payload,
            'passed' => $passed,
            'http_code' => $response['code'],
            'escaped' => !$hasXss,
        ];
        echo "$status Name: " . substr($payload, 0, 30) . " - HTTP {$response['code']}\n";
    }
    
    return $results;
}

// 执行测试
echo "=== API安全测试 ===\n";
echo "测试时间: " . date('Y-m-d H:i:s') . "\n";

$results['sql_injection'] = testSqlInjection($baseUrl, $sqlInjectionPayloads);
$results['xss'] = testXss($baseUrl, $xssPayloads);

// 统计结果
foreach ($results['sql_injection'] as $test) {
    $results['total']++;
    if ($test['passed']) $results['passed']++;
    else $results['failed']++;
}

foreach ($results['xss'] as $test) {
    $results['total']++;
    if ($test['passed']) $results['passed']++;
    else $results['failed']++;
}

// 输出总结
echo "\n=== 测试总结 ===\n";
echo "总测试数: {$results['total']}\n";
echo "通过: {$results['passed']} (" . round($results['passed'] / $results['total'] * 100, 2) . "%)\n";
echo "失败: {$results['failed']} (" . round($results['failed'] / $results['total'] * 100, 2) . "%)\n";

// SQL注入统计
$sqlPassed = count(array_filter($results['sql_injection'], fn($r) => $r['passed']));
$sqlTotal = count($results['sql_injection']);
echo "\nSQL注入防护: $sqlPassed/$sqlTotal (" . round($sqlPassed / $sqlTotal * 100, 2) . "%)\n";

// XSS统计
$xssPassed = count(array_filter($results['xss'], fn($r) => $r['passed']));
$xssTotal = count($results['xss']);
echo "XSS防护: $xssPassed/$xssTotal (" . round($xssPassed / $xssTotal * 100, 2) . "%)\n";

// 保存报告
$report = "# API安全测试报告\n\n";
$report .= "## 测试时间\n" . date('Y-m-d H:i:s') . "\n\n";
$report .= "## 测试结果\n\n";
$report .= "- 总测试数: {$results['total']}\n";
$report .= "- 通过: {$results['passed']} (" . round($results['passed'] / $results['total'] * 100, 2) . "%)\n";
$report .= "- 失败: {$results['failed']} (" . round($results['failed'] / $results['total'] * 100, 2) . "%)\n\n";
$report .= "## SQL注入防护\n\n";
$report .= "- 通过: $sqlPassed/$sqlTotal (" . round($sqlPassed / $sqlTotal * 100, 2) . "%)\n\n";
$report .= "## XSS防护\n\n";
$report .= "- 通过: $xssPassed/$xssTotal (" . round($xssPassed / $xssTotal * 100, 2) . "%)\n";

file_put_contents('storage/logs/api-security-test-report.md', $report);

echo "\n报告已保存到: storage/logs/api-security-test-report.md\n";
