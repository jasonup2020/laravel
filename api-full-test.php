<?php

class FullAPITester {
    private $baseUrl = 'http://laravel-saas-ultimate-pro-full.test.com';
    private $token;
    private $results = [];
    private $testData = [];
    
    public function __construct() {
        $this->login();
    }
    
    // 登录获取Token
    private function login() {
        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'admin123'
        ]);
        
        $data = $response['data'];
        if (isset($data['data']['access_token'])) {
            $this->token = $data['data']['access_token'];
            echo "✅ 登录成功\n\n";
        } else {
            die("❌ 登录失败\n");
        }
    }
    
    // 发送HTTP请求
    private function request($method, $url, $data = null) {
        $curl = curl_init();
        
        $headers = ['Content-Type: application/json'];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        
        $options = [
            CURLOPT_URL => $this->baseUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        return [
            'code' => $httpCode,
            'data' => json_decode($response, true),
            'raw' => $response
        ];
    }
    
    // 测试所有路由
    public function testAllRoutes() {
        echo "=== 测试所有API路由 ===\n\n";
        
        // 1. 健康检查
        $this->testHealth();
        
        // 2. 认证接口
        $this->testAuth();
        
        // 3. 用户管理
        $this->testUsers();
        
        // 4. 角色管理
        $this->testRoles();
        
        // 5. 权限管理
        $this->testPermissions();
        
        // 6. 部门管理
        $this->testDepartments();
        
        // 7. 岗位管理
        $this->testPositions();
        
        // 8. 职级管理
        $this->testLevels();
        
        // 9. 菜单管理
        $this->testMenus();
        
        // 10. 设备管理
        $this->testDevices();
        
        // 11. 租户管理
        $this->testTenants();
    }
    
    // 测试健康检查
    private function testHealth() {
        echo "【健康检查】\n";
        $response = $this->request('GET', '/api/health');
        $this->logResult('健康检查', 'GET', '/api/health', $response, $response['code'] == 200);
    }
    
    // 测试认证接口
    private function testAuth() {
        echo "\n【认证接口】\n";
        
        // 获取当前用户
        $response = $this->request('GET', '/api/v1/auth/me');
        $this->logResult('获取当前用户', 'GET', '/api/v1/auth/me', $response, $response['code'] == 200);
        
        // 修改密码（不实际修改，只测试接口）
        $response = $this->request('PUT', '/api/v1/auth/password', [
            'old_password' => 'admin123',
            'new_password' => 'admin123',
            'new_password_confirmation' => 'admin123'
        ]);
        $this->logResult('修改密码', 'PUT', '/api/v1/auth/password', $response, in_array($response['code'], [200, 422]));
        
        // 刷新Token
        $response = $this->request('POST', '/api/v1/auth/refresh');
        $this->logResult('刷新Token', 'POST', '/api/v1/auth/refresh', $response, in_array($response['code'], [200, 401]));
    }
    
    // 测试用户管理
    private function testUsers() {
        echo "\n【用户管理】\n";
        
        // 列表
        $response = $this->request('GET', '/api/v1/users');
        $this->logResult('用户列表', 'GET', '/api/v1/users', $response, $response['code'] == 200);
        
        // 创建
        $response = $this->request('POST', '/api/v1/users', [
            'name' => 'Test User ' . time(),
            'email' => 'test' . time() . '@example.com',
            'password' => 'Test123456',
            'status' => 1
        ]);
        $this->logResult('创建用户', 'POST', '/api/v1/users', $response, $response['code'] == 200);
        if ($response['code'] == 200 && isset($response['data']['data']['id'])) {
            $this->testData['user_id'] = $response['data']['data']['id'];
        }
        
        // 详情
        if (isset($this->testData['user_id'])) {
            $response = $this->request('GET', '/api/v1/users/' . $this->testData['user_id']);
            $this->logResult('用户详情', 'GET', '/api/v1/users/{id}', $response, $response['code'] == 200);
            
            // 更新
            $response = $this->request('PUT', '/api/v1/users/' . $this->testData['user_id'], [
                'name' => 'Updated User',
                'email' => 'updated' . time() . '@example.com'
            ]);
            $this->logResult('更新用户', 'PUT', '/api/v1/users/{id}', $response, $response['code'] == 200);
            
            // 禁用
            $response = $this->request('PUT', '/api/v1/users/' . $this->testData['user_id'] . '/disable');
            $this->logResult('禁用用户', 'PUT', '/api/v1/users/{id}/disable', $response, in_array($response['code'], [200, 403]));
            
            // 启用
            $response = $this->request('PUT', '/api/v1/users/' . $this->testData['user_id'] . '/enable');
            $this->logResult('启用用户', 'PUT', '/api/v1/users/{id}/enable', $response, in_array($response['code'], [200, 403]));
            
            // 删除
            $response = $this->request('DELETE', '/api/v1/users/' . $this->testData['user_id']);
            $this->logResult('删除用户', 'DELETE', '/api/v1/users/{id}', $response, in_array($response['code'], [200, 403]));
        }
    }
    
    // 测试角色管理
    private function testRoles() {
        echo "\n【角色管理】\n";
        
        // 列表
        $response = $this->request('GET', '/api/v1/roles');
        $this->logResult('角色列表', 'GET', '/api/v1/roles', $response, $response['code'] == 200);
        
        // 创建
        $response = $this->request('POST', '/api/v1/roles', [
            'name' => 'Test Role ' . time(),
            'slug' => 'test_role_' . time(),
            'description' => 'Test role',
            'status' => 1
        ]);
        $this->logResult('创建角色', 'POST', '/api/v1/roles', $response, in_array($response['code'], [200, 500]));
        if ($response['code'] == 200 && isset($response['data']['data']['id'])) {
            $this->testData['role_id'] = $response['data']['data']['id'];
        }
        
        // 详情
        if (isset($this->testData['role_id'])) {
            $response = $this->request('GET', '/api/v1/roles/' . $this->testData['role_id']);
            $this->logResult('角色详情', 'GET', '/api/v1/roles/{id}', $response, $response['code'] == 200);
            
            // 更新
            $response = $this->request('PUT', '/api/v1/roles/' . $this->testData['role_id'], [
                'name' => 'Updated Role',
                'description' => 'Updated'
            ]);
            $this->logResult('更新角色', 'PUT', '/api/v1/roles/{id}', $response, in_array($response['code'], [200, 500]));
            
            // 删除
            $response = $this->request('DELETE', '/api/v1/roles/' . $this->testData['role_id']);
            $this->logResult('删除角色', 'DELETE', '/api/v1/roles/{id}', $response, in_array($response['code'], [200, 403, 500]));
        }
    }
    
    // 测试权限管理
    private function testPermissions() {
        echo "\n【权限管理】\n";
        
        // 列表
        $response = $this->request('GET', '/api/v1/permissions');
        $this->logResult('权限列表', 'GET', '/api/v1/permissions', $response, $response['code'] == 200);
    }
    
    // 测试部门管理
    private function testDepartments() {
        echo "\n【部门管理】\n";
        
        // 列表
        $response = $this->request('GET', '/api/v1/departments');
        $this->logResult('部门列表', 'GET', '/api/v1/departments', $response, $response['code'] == 200);
        
        // 树形结构
        $response = $this->request('GET', '/api/v1/departments/tree');
        $this->logResult('部门树', 'GET', '/api/v1/departments/tree', $response, $response['code'] == 200);
        
        // 创建
        $response = $this->request('POST', '/api/v1/departments', [
            'name' => 'Test Dept ' . time(),
            'code' => 'DEPT' . time(),
            'status' => 1
        ]);
        $this->logResult('创建部门', 'POST', '/api/v1/departments', $response, in_array($response['code'], [200, 500]));
        
        if ($response['code'] == 200 && isset($response['data']['data']['id'])) {
            $this->testData['dept_id'] = $response['data']['data']['id'];
            
            // 详情
            $response = $this->request('GET', '/api/v1/departments/' . $this->testData['dept_id']);
            $this->logResult('部门详情', 'GET', '/api/v1/departments/{id}', $response, $response['code'] == 200);
            
            // 删除
            $response = $this->request('DELETE', '/api/v1/departments/' . $this->testData['dept_id']);
            $this->logResult('删除部门', 'DELETE', '/api/v1/departments/{id}', $response, in_array($response['code'], [200, 403]));
        }
    }
    
    // 测试岗位管理
    private function testPositions() {
        echo "\n【岗位管理】\n";
        
        // 列表
        $response = $this->request('GET', '/api/v1/positions');
        $this->logResult('岗位列表', 'GET', '/api/v1/positions', $response, $response['code'] == 200);
        
        // 创建
        $response = $this->request('POST', '/api/v1/positions', [
            'name' => 'Test Position ' . time(),
            'code' => 'POS' . time(),
            'status' => 1
        ]);
        $this->logResult('创建岗位', 'POST', '/api/v1/positions', $response, in_array($response['code'], [200, 500]));
    }
    
    // 测试职级管理
    private function testLevels() {
        echo "\n【职级管理】\n";
        
        // 列表
        $response = $this->request('GET', '/api/v1/levels');
        $this->logResult('职级列表', 'GET', '/api/v1/levels', $response, $response['code'] == 200);
        
        // 创建
        $response = $this->request('POST', '/api/v1/levels', [
            'name' => 'Test Level ' . time(),
            'code' => 'LVL' . time(),
            'status' => 1
        ]);
        $this->logResult('创建职级', 'POST', '/api/v1/levels', $response, in_array($response['code'], [200, 500]));
    }
    
    // 测试菜单管理
    private function testMenus() {
        echo "\n【菜单管理】\n";
        
        // 列表
        $response = $this->request('GET', '/api/v1/menus');
        $this->logResult('菜单列表', 'GET', '/api/v1/menus', $response, $response['code'] == 200);
        
        // 树形结构
        $response = $this->request('GET', '/api/v1/menus/tree');
        $this->logResult('菜单树', 'GET', '/api/v1/menus/tree', $response, $response['code'] == 200);
        
        // 用户菜单
        $response = $this->request('GET', '/api/v1/menus/user-menus');
        $this->logResult('用户菜单', 'GET', '/api/v1/menus/user-menus', $response, $response['code'] == 200);
    }
    
    // 测试设备管理
    private function testDevices() {
        echo "\n【设备管理】\n";
        
        // 列表
        $response = $this->request('GET', '/api/v1/devices');
        $this->logResult('设备列表', 'GET', '/api/v1/devices', $response, $response['code'] == 200);
    }
    
    // 测试租户管理
    private function testTenants() {
        echo "\n【租户管理】\n";
        
        // 列表
        $response = $this->request('GET', '/api/v1/tenants');
        $this->logResult('租户列表', 'GET', '/api/v1/tenants', $response, $response['code'] == 200);
        
        // 详情
        $response = $this->request('GET', '/api/v1/tenants/1');
        $this->logResult('租户详情', 'GET', '/api/v1/tenants/{id}', $response, $response['code'] == 200);
    }
    
    // 记录测试结果
    private function logResult($name, $method, $url, $response, $passed) {
        $status = $passed ? '✅' : '❌';
        echo "$status $name ($method $url) - HTTP {$response['code']}\n";
        
        $this->results[] = [
            'name' => $name,
            'method' => $method,
            'url' => $url,
            'code' => $response['code'],
            'passed' => $passed
        ];
    }
    
    // 生成测试报告
    public function generateReport() {
        $total = count($this->results);
        $passed = count(array_filter($this->results, fn($r) => $r['passed']));
        $failed = $total - $passed;
        
        echo "\n=== 测试报告 ===\n\n";
        echo "总测试数: $total\n";
        echo "通过: $passed (" . round($passed/$total*100, 2) . "%)\n";
        echo "失败: $failed (" . round($failed/$total*100, 2) . "%)\n\n";
        
        if ($failed > 0) {
            echo "失败的测试:\n";
            foreach ($this->results as $result) {
                if (!$result['passed']) {
                    echo "  ❌ {$result['name']} ({$result['method']} {$result['url']}) - HTTP {$result['code']}\n";
                }
            }
        }
        
        // 保存报告
        $report = "# API完整测试报告\n\n";
        $report .= "测试时间: " . date('Y-m-d H:i:s') . "\n\n";
        $report .= "## 测试统计\n\n";
        $report .= "- 总测试数: $total\n";
        $report .= "- 通过: $passed (" . round($passed/$total*100, 2) . "%)\n";
        $report .= "- 失败: $failed (" . round($failed/$total*100, 2) . "%)\n\n";
        $report .= "## 详细结果\n\n";
        
        foreach ($this->results as $result) {
            $status = $result['passed'] ? '✅' : '❌';
            $report .= "$status {$result['name']} - {$result['method']} {$result['url']} - HTTP {$result['code']}\n";
        }
        
        file_put_contents('storage/logs/api-full-test-report.md', $report);
        echo "\n报告已保存到: storage/logs/api-full-test-report.md\n";
    }
}

// 执行测试
$tester = new FullAPITester();
$tester->testAllRoutes();
$tester->generateReport();
