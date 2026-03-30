<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RateLimitConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requests_per_minute' => 'nullable|integer|min:1|max:10000',
            'requests_per_hour' => 'nullable|integer|min:1|max:100000',
            'requests_per_day' => 'nullable|integer|min:1|max:1000000',
        ];
    }

    public function messages(): array
    {
        return [
            'requests_per_minute.integer' => __('validation.integer', ['attribute' => 'requests_per_minute']),
            'requests_per_minute.min' => __('validation.min.numeric', ['attribute' => 'requests_per_minute', 'min' => 1]),
            'requests_per_hour.integer' => __('validation.integer', ['attribute' => 'requests_per_hour']),
            'requests_per_day.integer' => __('validation.integer', ['attribute' => 'requests_per_day']),
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
