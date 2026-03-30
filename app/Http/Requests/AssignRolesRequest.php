<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AssignRolesRequest 验证请求
 */
class AssignRolesRequest extends FormRequest
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
                        'role_ids' => 'required|array',
                        'role_ids.*' => 'integer|exists:roles,id',
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
                        'role_ids.required' => '角色ID不能为空',
                        'role_ids.array' => '角色ID必须为数组',
                        'role_ids.*.integer' => '角色ID必须为整数',
                        'role_ids.*.exists' => '角色不存在',
                    ];
    }
}