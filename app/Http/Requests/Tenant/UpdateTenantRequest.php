<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 更新租户请求验证
 */
class UpdateTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $tenantId = $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('tenants', 'code')->ignore($tenantId)],
            'domain' => ['nullable', 'string', 'max:100', Rule::unique('tenants', 'domain')->ignore($tenantId)],
            'logo' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:50'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            'expire_at' => ['nullable', 'date'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => '租户名称不能为空',
            'name.max' => '租户名称不能超过100个字符',
            'code.required' => '租户编码不能为空',
            'code.max' => '租户编码不能超过50个字符',
            'code.unique' => '租户编码已存在',
            'domain.unique' => '租户域名已存在',
            'contact_email.email' => '联系人邮箱格式不正确',
            'expire_at.date' => '过期时间格式不正确',
            'status.in' => '状态值不正确',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'name' => '租户名称',
            'code' => '租户编码',
            'domain' => '租户域名',
            'logo' => '租户Logo',
            'contact_name' => '联系人姓名',
            'contact_phone' => '联系人电话',
            'contact_email' => '联系人邮箱',
            'address' => '联系地址',
            'config' => '租户配置',
            'expire_at' => '过期时间',
            'status' => '状态',
        ];
    }
}
