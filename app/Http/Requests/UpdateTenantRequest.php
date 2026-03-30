<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('id');
        return [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:tenants,code,' . $tenantId,
            'domain' => 'nullable|string|max:255',
            'database' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => __('validation.string', ['attribute' => 'name']),
            'code.unique' => __('validation.unique', ['attribute' => 'code']),
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
