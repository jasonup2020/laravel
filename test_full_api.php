<?php

/**
 * 完整API接口测试脚本
 * 按照 docs/API.md 文档测试所有接口
 */

$baseUrl = 'http://laravel-saas-ultimate-pro-full.test.com/api/v1';
$testResults = [];
$testDetails = [];
$token = null;
$refreshToken = null;

echo "\n" . str_repeat("=", 100) . "\n";
echo "完整API接口测试报告 - 按照 docs/API.md 文档测试\n";
echo str_repeat("=", 100) . "\n";
echo "测试时间: " . date('Y-m-d H:i:s') . "\n";
echo "基础URL: {$baseUrl}\n";
echo str_repeat("=", 100) . "\n\n";

// 测试函数
function testApi($name, $method, $url, $data = null, $headers = [], $expectedCode = 200) {
    global $testResults, $testDetails;
    
    echo "\n" . str_repeat("-", 80) . "\n";
    echo "测试: {$name}\n";
    echo "请求: {$method} {$url}\n";
    if ($data) {
        echo "数据: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    }
    
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
    
    $success = in_array($httpCode, [$expectedCode, 200, 201]);
    
    $result = [
        'name' => $name,
        'method' => $method,
        'url' => $url,
        'status_code' => $httpCode,
        'expected_code' => $expectedCode,
        'response' => $response,
        'error' => $error,
        'success' => $success,
    ];
    
    $testResults[] = $result;
    
    if ($success) {
        echo "✅ PASS - 状态码: {$httpCode}\n";
        $testDetails[] = "✅ {$name}: 成功 (状态码: {$httpCode})";
    } else {
        echo "❌ FAIL - 预期: {$expectedCode}, 实际: {$httpCode}\n";
        if ($error) {
            echo "错误: {$error}\n";
        }
        $testDetails[] = "❌ {$name}: 失败 (预期: {$expectedCode}, 实际: {$httpCode})";
    }
    
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo "响应: " . json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "响应: " . substr($response, 0, 500) . "\n";
    }
    
    return $result;
}

// ==================== 测试开始 ====================

// 1. 健康检查
echo "\n\n" . str_repeat("=", 100) . "\n";
echo "1. 健康检查测试\n";
echo str_repeat("=", 100) . "\n";
testApi('健康检查', 'GET', 'http://laravel-saas-ultimate-pro-full.test.com/api/health');

// 2. 认证模块测试
echo "\n\n" . str_repeat("=", 100) . "\n";
echo "2. 认证模块测试 (AuthController)\n";
echo str_repeat("=", 100) . "\n";

// 2.1 用户登录
$loginResult = testApi('用户登录', 'POST', "{$baseUrl}/auth/login", [
    'email' => 'admin@example.com',
    'password' => 'admin123'
], [], 200);

if ($loginResult['success']) {
    $responseData = json_decode($loginResult['response'], true);
    $token = $responseData['data']['access_token'] ?? null;
    $refreshToken = $responseData['data']['refresh_token'] ?? null;
    echo "\n获取到Token: " . substr($token ?? '', 0, 30) . "...\n";
}

// 2.2 用户注册
testApi('用户注册', 'POST', "{$baseUrl}/auth/register", [
    'name' => 'testuser' . time(),
    'email' => 'test' . time() . '@example.com',
    'password' => 'password123'
], [], 201);

// 2.3 获取当前用户信息
if ($token) {
    $authHeaders = ["Authorization: Bearer {$token}"];
    testApi('获取当前用户信息', 'GET', "{$baseUrl}/auth/me", null, $authHeaders);
}

// 2.4 刷新Token
if ($refreshToken) {
    testApi('刷新Token', 'POST', "{$baseUrl}/auth/refresh", [
        'refresh_token' => $refreshToken
    ], [], 200);
}

// 2.5 用户登出
if ($token) {
    testApi('用户登出', 'POST', "{$baseUrl}/auth/logout", null, $authHeaders);
}

// 重新登录获取新Token
$loginResult = testApi('重新登录', 'POST', "{$baseUrl}/auth/login", [
    'email' => 'admin@example.com',
    'password' => 'admin123'
], [], 200);

if ($loginResult['success']) {
    $responseData = json_decode($loginResult['response'], true);
    $token = $responseData['data']['access_token'] ?? null;
    $authHeaders = ["Authorization: Bearer {$token}"];
}

// 3. 用户模块测试
echo "\n\n" . str_repeat("=", 100) . "\n";
echo "3. 用户模块测试 (UserController)\n";
echo str_repeat("=", 100) . "\n";

if ($token) {
    // 3.1 获取用户列表
    testApi('获取用户列表', 'GET', "{$baseUrl}/users?page=1&per_page=10", null, $authHeaders);
    
    // 3.2 创建用户
    $createUserResult = testApi('创建用户', 'POST', "{$baseUrl}/users", [
        'name' => 'Test User ' . time(),
        'email' => 'testuser' . time() . '@example.com',
        'password' => 'password123'
    ], $authHeaders, 200);
    
    // 3.3 获取用户详情
    testApi('获取用户详情', 'GET', "{$baseUrl}/users/1", null, $authHeaders);
    
    // 3.4 更新用户
    testApi('更新用户', 'PUT', "{$baseUrl}/users/1", [
        'name' => 'Updated User ' . time()
    ], $authHeaders);
    
    // 3.5 删除用户（创建一个新用户然后删除）
    $newUserResult = testApi('创建待删除用户', 'POST', "{$baseUrl}/users", [
        'name' => 'Delete User ' . time(),
        'email' => 'deleteuser' . time() . '@example.com',
        'password' => 'password123'
    ], $authHeaders, 200);
    
    if ($newUserResult['success']) {
        $newUserData = json_decode($newUserResult['response'], true);
        $newUserId = $newUserData['data']['id'] ?? null;
        if ($newUserId) {
            testApi('删除用户', 'DELETE', "{$baseUrl}/users/{$newUserId}", null, $authHeaders);
        }
    }
}

// 4. 租户模块测试
echo "\n\n" . str_repeat("=", 100) . "\n";
echo "4. 租户模块测试 (TenantController)\n";
echo str_repeat("=", 100) . "\n";

if ($token) {
    // 4.1 获取租户列表
    testApi('获取租户列表', 'GET', "{$baseUrl}/tenants?page=1&per_page=10", null, $authHeaders);
    
    // 4.2 创建租户
    $createTenantResult = testApi('创建租户', 'POST', "{$baseUrl}/tenants", [
        'name' => 'Test Company ' . time(),
        'code' => 'TEST' . time(),
        'domain' => 'test' . time() . '.example.com'
    ], $authHeaders);
    
    // 4.3 获取租户详情
    testApi('获取租户详情', 'GET', "{$baseUrl}/tenants/1", null, $authHeaders);
    
    // 4.4 更新租户
    testApi('更新租户', 'PUT', "{$baseUrl}/tenants/1", [
        'name' => 'Updated Company ' . time()
    ], $authHeaders);
    
    // 4.5 删除租户（创建一个新租户然后删除）
    $newTenantResult = testApi('创建待删除租户', 'POST', "{$baseUrl}/tenants", [
        'name' => 'Delete Company ' . time(),
        'code' => 'DEL' . time(),
        'domain' => 'del' . time() . '.example.com'
    ], $authHeaders, 200);
    
    if ($newTenantResult['success']) {
        $newTenantData = json_decode($newTenantResult['response'], true);
        $newTenantId = $newTenantData['data']['id'] ?? null;
        if ($newTenantId) {
            testApi('删除租户', 'DELETE', "{$baseUrl}/tenants/{$newTenantId}", null, $authHeaders);
        }
    }
}

// 5. 角色模块测试
echo "\n\n" . str_repeat("=", 100) . "\n";
echo "5. 角色模块测试 (RoleController)\n";
echo str_repeat("=", 100) . "\n";

if ($token) {
    // 5.1 获取角色列表
    testApi('获取角色列表', 'GET', "{$baseUrl}/roles?page=1&per_page=10", null, $authHeaders);
    
    // 5.2 创建角色
    testApi('创建角色', 'POST', "{$baseUrl}/roles", [
        'name' => 'Test Role ' . time(),
        'slug' => 'test-role-' . time()
    ], $authHeaders);
    
    // 5.3 获取角色详情
    testApi('获取角色详情', 'GET', "{$baseUrl}/roles/1", null, $authHeaders);
    
    // 5.4 更新角色
    testApi('更新角色', 'PUT', "{$baseUrl}/roles/1", [
        'name' => 'Updated Role ' . time()
    ], $authHeaders);
    
    // 5.5 删除角色
    $newRoleResult = testApi('创建待删除角色', 'POST', "{$baseUrl}/roles", [
        'name' => 'Delete Role ' . time(),
        'slug' => 'delete-role-' . time()
    ], $authHeaders, 200);
    
    if ($newRoleResult['success']) {
        $newRoleData = json_decode($newRoleResult['response'], true);
        $newRoleId = $newRoleData['data']['id'] ?? null;
        if ($newRoleId) {
            testApi('删除角色', 'DELETE', "{$baseUrl}/roles/{$newRoleId}", null, $authHeaders);
        }
    }
}

// 6. 权限模块测试
echo "\n\n" . str_repeat("=", 100) . "\n";
echo "6. 权限模块测试 (PermissionController)\n";
echo str_repeat("=", 100) . "\n";

if ($token) {
    // 6.1 获取权限列表
    testApi('获取权限列表', 'GET', "{$baseUrl}/permissions?page=1&per_page=10", null, $authHeaders);
    
    // 6.2 创建权限
    testApi('创建权限', 'POST', "{$baseUrl}/permissions", [
        'name' => 'Test Permission ' . time(),
        'slug' => 'test-permission-' . time(),
        'module' => 'test'
    ], $authHeaders);
    
    // 6.3 获取权限详情
    testApi('获取权限详情', 'GET', "{$baseUrl}/permissions/1", null, $authHeaders);
    
    // 6.4 更新权限
    testApi('更新权限', 'PUT', "{$baseUrl}/permissions/1", [
        'name' => 'Updated Permission ' . time()
    ], $authHeaders);
    
    // 6.5 删除权限
    $newPermResult = testApi('创建待删除权限', 'POST', "{$baseUrl}/permissions", [
        'name' => 'Delete Permission ' . time(),
        'slug' => 'delete-permission-' . time(),
        'module' => 'test'
    ], $authHeaders, 200);
    
    if ($newPermResult['success']) {
        $newPermData = json_decode($newPermResult['response'], true);
        $newPermId = $newPermData['data']['id'] ?? null;
        if ($newPermId) {
            testApi('删除权限', 'DELETE', "{$baseUrl}/permissions/{$newPermId}", null, $authHeaders);
        }
    }
}

// 7. 部门模块测试
echo "\n\n" . str_repeat("=", 100) . "\n";
echo "7. 部门模块测试 (DepartmentController)\n";
echo str_repeat("=", 100) . "\n";

if ($token) {
    // 7.1 获取部门列表
    testApi('获取部门列表', 'GET', "{$baseUrl}/departments?page=1&per_page=10", null, $authHeaders);
    
    // 7.2 获取部门树形结构
    testApi('获取部门树形结构', 'GET', "{$baseUrl}/departments/tree", null, $authHeaders);
    
    // 7.3 创建部门
    testApi('创建部门', 'POST', "{$baseUrl}/departments", [
        'name' => 'Test Department ' . time(),
        'code' => 'TEST-DEPT-' . time()
    ], $authHeaders);
    
    // 7.4 获取部门详情
    testApi('获取部门详情', 'GET', "{$baseUrl}/departments/1", null, $authHeaders);
    
    // 7.5 更新部门
    testApi('更新部门', 'PUT', "{$baseUrl}/departments/1", [
        'name' => 'Updated Department ' . time()
    ], $authHeaders);
    
    // 7.6 删除部门
    $newDeptResult = testApi('创建待删除部门', 'POST', "{$baseUrl}/departments", [
        'name' => 'Delete Department ' . time(),
        'code' => 'DEL-DEPT-' . time()
    ], $authHeaders, 200);
    
    if ($newDeptResult['success']) {
        $newDeptData = json_decode($newDeptResult['response'], true);
        $newDeptId = $newDeptData['data']['id'] ?? null;
        if ($newDeptId) {
            testApi('删除部门', 'DELETE', "{$baseUrl}/departments/{$newDeptId}", null, $authHeaders);
        }
    }
}

// 8. 岗位模块测试
echo "\n\n" . str_repeat("=", 100) . "\n";
echo "8. 岗位模块测试 (PositionController)\n";
echo str_repeat("=", 100) . "\n";

if ($token) {
    // 8.1 获取岗位列表
    testApi('获取岗位列表', 'GET', "{$baseUrl}/positions?page=1&per_page=10", null, $authHeaders);
    
    // 8.2 创建岗位
    testApi('创建岗位', 'POST', "{$baseUrl}/positions", [
        'name' => 'Test Position ' . time(),
        'code' => 'TEST-POS-' . time()
    ], $authHeaders);
    
    // 8.3 获取岗位详情
    testApi('获取岗位详情', 'GET', "{$baseUrl}/positions/1", null, $authHeaders);
    
    // 8.4 更新岗位
    testApi('更新岗位', 'PUT', "{$baseUrl}/positions/1", [
        'name' => 'Updated Position ' . time()
    ], $authHeaders);
    
    // 8.5 删除岗位
    $newPosResult = testApi('创建待删除岗位', 'POST', "{$baseUrl}/positions", [
        'name' => 'Delete Position ' . time(),
        'code' => 'DEL-POS-' . time()
    ], $authHeaders, 200);
    
    if ($newPosResult['success']) {
        $newPosData = json_decode($newPosResult['response'], true);
        $newPosId = $newPosData['data']['id'] ?? null;
        if ($newPosId) {
            testApi('删除岗位', 'DELETE', "{$baseUrl}/positions/{$newPosId}", null, $authHeaders);
        }
    }
}

// 9. 职级模块测试
echo "\n\n" . str_repeat("=", 100) . "\n";
echo "9. 职级模块测试 (LevelController)\n";
echo str_repeat("=", 100) . "\n";

if ($token) {
    // 9.1 获取职级列表
    testApi('获取职级列表', 'GET', "{$baseUrl}/levels?page=1&per_page=10", null, $authHeaders);
    
    // 9.2 创建职级
    testApi('创建职级', 'POST', "{$baseUrl}/levels", [
        'name' => 'Test Level ' . time(),
        'code' => 'TEST-LEVEL-' . time()
    ], $authHeaders);
    
    // 9.3 获取职级详情
    testApi('获取职级详情', 'GET', "{$baseUrl}/levels/1", null, $authHeaders);
    
    // 9.4 更新职级
    testApi('更新职级', 'PUT', "{$baseUrl}/levels/1", [
        'name' => 'Updated Level ' . time()
    ], $authHeaders);
    
    // 9.5 删除职级
    $newLevelResult = testApi('创建待删除职级', 'POST', "{$baseUrl}/levels", [
        'name' => 'Delete Level ' . time(),
        'code' => 'DEL-LEVEL-' . time()
    ], $authHeaders, 200);
    
    if ($newLevelResult['success']) {
        $newLevelData = json_decode($newLevelResult['response'], true);
        $newLevelId = $newLevelData['data']['id'] ?? null;
        if ($newLevelId) {
            testApi('删除职级', 'DELETE', "{$baseUrl}/levels/{$newLevelId}", null, $authHeaders);
        }
    }
}

// ==================== 生成报告 ====================

echo "\n\n" . str_repeat("=", 100) . "\n";
echo "测试结果汇总\n";
echo str_repeat("=", 100) . "\n\n";

$passCount = count(array_filter($testResults, fn($r) => $r['success']));
$failCount = count($testResults) - $passCount;
$totalCount = count($testResults);

echo "总测试数: {$totalCount}\n";
echo "通过: {$passCount}\n";
echo "失败: {$failCount}\n";
echo "通过率: " . round($passCount / $totalCount * 100, 2) . "%\n\n";

echo "测试明细:\n";
echo str_repeat("-", 100) . "\n";
foreach ($testDetails as $detail) {
    echo $detail . "\n";
}

// 保存报告到文件
$reportContent = "# 完整API接口测试报告\n\n";
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
    $reportContent .= "- 预期状态码: {$result['expected_code']}\n";
    $reportContent .= "- 实际状态码: {$result['status_code']}\n";
    if ($result['error']) {
        $reportContent .= "- 错误: {$result['error']}\n";
    }
    $reportContent .= "\n";
}

file_put_contents(__DIR__ . '/docs/MD/完整API测试报告.md', $reportContent);

echo "\n报告已保存到: docs/MD/完整API测试报告.md\n";
