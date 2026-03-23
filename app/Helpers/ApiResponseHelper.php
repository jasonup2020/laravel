<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

trait ApiResponseHelper
{
    protected function successResponse($data = null, string $message = '', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    protected function errorResponse(string $message = '', int $statusCode = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors'  => $errors,
        ], $statusCode);
    }
}
