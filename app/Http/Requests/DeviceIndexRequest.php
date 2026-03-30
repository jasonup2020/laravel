<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DeviceIndexRequest 验证请求
 */
class DeviceIndexRequest extends FormRequest
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
                        'user_id' => 'nullable|integer|exists:users,id',
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
                        'user_id.integer' => '用户ID必须为整数',
                        'user_id.exists' => '用户不存在',
                        'status.integer' => '状态必须为整数',
                        'status.in' => '状态值无效',
                        'per_page.integer' => '每页数量必须为整数',
                        'per_page.min' => '每页数量不能小于1',
                        'per_page.max' => '每页数量不能超过100',
                    ];
    }
}