<?php

/**
 * 批量创建FormRequest验证类
 */

$requests = [
    // 用户相关
    'UserIndexRequest' => [
        'rules' => [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|integer|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ],
        'messages' => [
            'name.string' => '名称必须为字符串',
            'name.max' => '名称长度不能超过255个字符',
            'email.email' => '邮箱格式不正确',
            'email.max' => '邮箱长度不能超过255个字符',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
            'per_page.integer' => '每页数量必须为整数',
            'per_page.min' => '每页数量不能小于1',
            'per_page.max' => '每页数量不能超过100',
        ],
    ],
    
    'AssignRolesRequest' => [
        'rules' => [
            'role_ids' => 'required|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ],
        'messages' => [
            'role_ids.required' => '角色ID不能为空',
            'role_ids.array' => '角色ID必须为数组',
            'role_ids.*.integer' => '角色ID必须为整数',
            'role_ids.*.exists' => '角色不存在',
        ],
    ],
    
    // 角色相关
    'RoleIndexRequest' => [
        'rules' => [
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:100',
            'status' => 'nullable|integer|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ],
        'messages' => [
            'name.string' => '名称必须为字符串',
            'name.max' => '名称长度不能超过255个字符',
            'slug.string' => '标识必须为字符串',
            'slug.max' => '标识长度不能超过100个字符',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
            'per_page.integer' => '每页数量必须为整数',
            'per_page.min' => '每页数量不能小于1',
            'per_page.max' => '每页数量不能超过100',
        ],
    ],
    
    'UpdateRoleRequest' => [
        'rules' => [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'nullable|integer|in:0,1',
        ],
        'messages' => [
            'name.required' => '名称不能为空',
            'name.string' => '名称必须为字符串',
            'name.max' => '名称长度不能超过255个字符',
            'slug.required' => '标识不能为空',
            'slug.string' => '标识必须为字符串',
            'slug.max' => '标识长度不能超过100个字符',
            'description.string' => '描述必须为字符串',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
        ],
    ],
    
    'AssignPermissionsRequest' => [
        'rules' => [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ],
        'messages' => [
            'permission_ids.required' => '权限ID不能为空',
            'permission_ids.array' => '权限ID必须为数组',
            'permission_ids.*.integer' => '权限ID必须为整数',
            'permission_ids.*.exists' => '权限不存在',
        ],
    ],
    
    // 权限相关
    'PermissionIndexRequest' => [
        'rules' => [
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:100',
            'status' => 'nullable|integer|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ],
        'messages' => [
            'name.string' => '名称必须为字符串',
            'name.max' => '名称长度不能超过255个字符',
            'slug.string' => '标识必须为字符串',
            'slug.max' => '标识长度不能超过100个字符',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
            'per_page.integer' => '每页数量必须为整数',
            'per_page.min' => '每页数量不能小于1',
            'per_page.max' => '每页数量不能超过100',
        ],
    ],
    
    // 菜单相关
    'MenuIndexRequest' => [
        'rules' => [
            'name' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ],
        'messages' => [
            'name.string' => '名称必须为字符串',
            'name.max' => '名称长度不能超过255个字符',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
            'per_page.integer' => '每页数量必须为整数',
            'per_page.min' => '每页数量不能小于1',
            'per_page.max' => '每页数量不能超过100',
        ],
    ],
    
    // 部门相关
    'DepartmentIndexRequest' => [
        'rules' => [
            'name' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ],
        'messages' => [
            'name.string' => '名称必须为字符串',
            'name.max' => '名称长度不能超过255个字符',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
            'per_page.integer' => '每页数量必须为整数',
            'per_page.min' => '每页数量不能小于1',
            'per_page.max' => '每页数量不能超过100',
        ],
    ],
    
    // 岗位相关
    'PositionIndexRequest' => [
        'rules' => [
            'name' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ],
        'messages' => [
            'name.string' => '名称必须为字符串',
            'name.max' => '名称长度不能超过255个字符',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
            'per_page.integer' => '每页数量必须为整数',
            'per_page.min' => '每页数量不能小于1',
            'per_page.max' => '每页数量不能超过100',
        ],
    ],
    
    // 职级相关
    'LevelIndexRequest' => [
        'rules' => [
            'name' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ],
        'messages' => [
            'name.string' => '名称必须为字符串',
            'name.max' => '名称长度不能超过255个字符',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
            'per_page.integer' => '每页数量必须为整数',
            'per_page.min' => '每页数量不能小于1',
            'per_page.max' => '每页数量不能超过100',
        ],
    ],
    
    // 设备相关
    'DeviceIndexRequest' => [
        'rules' => [
            'user_id' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|integer|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ],
        'messages' => [
            'user_id.integer' => '用户ID必须为整数',
            'user_id.exists' => '用户不存在',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
            'per_page.integer' => '每页数量必须为整数',
            'per_page.min' => '每页数量不能小于1',
            'per_page.max' => '每页数量不能超过100',
        ],
    ],
    
    // 租户相关
    'TenantIndexRequest' => [
        'rules' => [
            'name' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ],
        'messages' => [
            'name.string' => '名称必须为字符串',
            'name.max' => '名称长度不能超过255个字符',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
            'per_page.integer' => '每页数量必须为整数',
            'per_page.min' => '每页数量不能小于1',
            'per_page.max' => '每页数量不能超过100',
        ],
    ],
    
    'StoreTenantRequest' => [
        'rules' => [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:tenants',
            'domain' => 'nullable|string|max:255|unique:tenants',
            'status' => 'nullable|integer|in:0,1',
        ],
        'messages' => [
            'name.required' => '名称不能为空',
            'name.string' => '名称必须为字符串',
            'name.max' => '名称长度不能超过255个字符',
            'slug.required' => '标识不能为空',
            'slug.string' => '标识必须为字符串',
            'slug.max' => '标识长度不能超过100个字符',
            'slug.unique' => '标识已存在',
            'domain.string' => '域名必须为字符串',
            'domain.max' => '域名长度不能超过255个字符',
            'domain.unique' => '域名已存在',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
        ],
    ],
    
    'UpdateTenantRequest' => [
        'rules' => [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100',
            'domain' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
        ],
        'messages' => [
            'name.required' => '名称不能为空',
            'name.string' => '名称必须为字符串',
            'name.max' => '名称长度不能超过255个字符',
            'slug.required' => '标识不能为空',
            'slug.string' => '标识必须为字符串',
            'slug.max' => '标识长度不能超过100个字符',
            'domain.string' => '域名必须为字符串',
            'domain.max' => '域名长度不能超过255个字符',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态值无效',
        ],
    ],
    
    'TenantConfigRequest' => [
        'rules' => [
            'config' => 'required|array',
        ],
        'messages' => [
            'config.required' => '配置不能为空',
            'config.array' => '配置必须为数组',
        ],
    ],
    
    // 限流相关
    'RateLimitConfigRequest' => [
        'rules' => [
            'rate_limits' => 'required|array',
            'rate_limits.*.endpoint' => 'required|string',
            'rate_limits.*.max_attempts' => 'required|integer|min:1',
            'rate_limits.*.decay_minutes' => 'required|integer|min:1',
        ],
        'messages' => [
            'rate_limits.required' => '限流配置不能为空',
            'rate_limits.array' => '限流配置必须为数组',
            'rate_limits.*.endpoint.required' => '端点不能为空',
            'rate_limits.*.endpoint.string' => '端点必须为字符串',
            'rate_limits.*.max_attempts.required' => '最大尝试次数不能为空',
            'rate_limits.*.max_attempts.integer' => '最大尝试次数必须为整数',
            'rate_limits.*.max_attempts.min' => '最大尝试次数不能小于1',
            'rate_limits.*.decay_minutes.required' => '衰减时间不能为空',
            'rate_limits.*.decay_minutes.integer' => '衰减时间必须为整数',
            'rate_limits.*.decay_minutes.min' => '衰减时间不能小于1',
        ],
    ],
    
    'RateLimitResetRequest' => [
        'rules' => [
            'endpoint' => 'nullable|string',
        ],
        'messages' => [
            'endpoint.string' => '端点必须为字符串',
        ],
    ],
];

// 生成文件
foreach ($requests as $className => $config) {
    $content = generateRequestClass($className, $config['rules'], $config['messages']);
    $filename = "app/Http/Requests/{$className}.php";
    file_put_contents($filename, $content);
    echo "✅ 创建: {$filename}\n";
}

echo "\n✅ 所有验证类创建完成！\n";

function generateRequestClass(string $className, array $rules, array $messages): string {
    $rulesString = formatArray($rules, 3);
    $messagesString = formatArray($messages, 3);
    
    return <<<PHP
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * {$className} 验证请求
 */
class {$className} extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return {$rulesString};
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return {$messagesString};
    }
}
PHP;
}

function formatArray(array $array, int $indent): string {
    $spaces = str_repeat('    ', $indent);
    $lines = ["["];
    
    foreach ($array as $key => $value) {
        $lines[] = "{$spaces}'{$key}' => '{$value}',";
    }
    
    $lines[] = str_repeat('    ', $indent - 1) . "]";
    
    return implode("\n{$spaces}", $lines);
}
