<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 修改密码请求验证
 */
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'max:20', 'confirmed'],
            'new_password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'old_password.required' => '原密码不能为空',
            'new_password.required' => '新密码不能为空',
            'new_password.min' => '新密码不能少于6个字符',
            'new_password.max' => '新密码不能超过20个字符',
            'new_password.confirmed' => '两次输入的密码不一致',
            'new_password_confirmation.required' => '确认密码不能为空',
        ];
    }
}
