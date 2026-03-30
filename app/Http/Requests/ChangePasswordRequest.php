<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 修改密码验证请求
 */
class ChangePasswordRequest extends FormRequest
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
        return [
            'old_password' => 'required|string|min:6|max:255',
            'new_password' => 'required|string|min:6|max:255|different:old_password',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'old_password.required' => '原密码不能为空',
            'old_password.string' => '原密码必须为字符串',
            'old_password.min' => '原密码长度不能少于6个字符',
            'old_password.max' => '原密码长度不能超过255个字符',
            'new_password.required' => '新密码不能为空',
            'new_password.string' => '新密码必须为字符串',
            'new_password.min' => '新密码长度不能少于6个字符',
            'new_password.max' => '新密码长度不能超过255个字符',
            'new_password.different' => '新密码不能与原密码相同',
        ];
    }
}
