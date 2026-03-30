<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssignPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'permission_ids.required' => '权限ID不能为空',
            'permission_ids.array' => '权限ID必须为数组',
            'permission_ids.*.integer' => '权限ID必须为整数',
            'permission_ids.*.exists' => '权限不存在',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
