<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UserIndexRequest 验证请求
 */
class UserIndexRequest extends FormRequest
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
                        'name' => 'nullable|string|max:255',
                        'email' => 'nullable|email|max:255',
                        'status' => 'nullable|integer|in:0,1',
                        'per_page' => 'nullable|integer|min:1|max:100',
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
                        'name.string' => '名称必须为字符串',
                        'name.max' => '名称长度不能超过255个字符',
                        'email.email' => '邮箱格式不正确',
                        'email.max' => '邮箱长度不能超过255个字符',
                        'status.integer' => '状态必须为整数',
                        'status.in' => '状态值无效',
                        'per_page.integer' => '每页数量必须为整数',
                        'per_page.min' => '每页数量不能小于1',
                        'per_page.max' => '每页数量不能超过100',
                    ];
    }
}