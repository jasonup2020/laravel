<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 创建用户请求验证
 */
class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6', 'max:20'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'integer', 'in:0,1,2'],
            'birthday' => ['nullable', 'date'],
            'signature' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'level_id' => ['nullable', 'integer', 'exists:levels,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => '用户名不能为空',
            'username.max' => '用户名不能超过50个字符',
            'email.email' => '邮箱格式不正确',
            'phone.max' => '手机号不能超过20个字符',
            'password.required' => '密码不能为空',
            'password.min' => '密码不能少于6个字符',
            'password.max' => '密码不能超过20个字符',
            'gender.in' => '性别值不正确',
            'birthday.date' => '生日格式不正确',
            'department_id.exists' => '部门不存在',
            'position_id.exists' => '岗位不存在',
            'level_id.exists' => '职级不存在',
            'role_ids.*.exists' => '角色不存在',
            'status.in' => '状态值不正确',
        ];
    }
}
