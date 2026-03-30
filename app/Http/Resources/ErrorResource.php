<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 错误资源
 */
class ErrorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'success' => false,
            'code' => $this->resource['code'] ?? -1,
            'message' => $this->resource['message'] ?? '操作失败',
            'data' => $this->resource['data'] ?? null,
        ];
    }
}
