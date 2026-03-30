<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 成功资源
 */
class SuccessResource extends JsonResource
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
            'success' => true,
            'code' => $this->resource['code'] ?? 0,
            'message' => $this->resource['message'] ?? '操作成功',
            'data' => $this->resource['data'] ?? null,
        ];
    }
}
