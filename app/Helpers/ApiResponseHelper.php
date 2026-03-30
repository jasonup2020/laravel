<?php

namespace App\Helpers;

trait ApiResponseHelper
{
    /**
     * Success response
     */
    protected function successResponse($data = null, $message = 'Success', $code = 200)
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Error response
     */
    protected function errorResponse($message = 'Error', $code = 400, $data = null)
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
