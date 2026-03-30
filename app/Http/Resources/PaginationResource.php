<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 分页资源
 */
class PaginationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        if ($this->resource instanceof LengthAwarePaginator) {
            return [
                'list' => $this->resource->items(),
                'total' => $this->resource->total(),
                'page' => $this->resource->currentPage(),
                'page_size' => $this->resource->perPage(),
                'total_pages' => $this->resource->lastPage(),
                'has_more' => $this->resource->hasMorePages(),
            ];
        }

        return [
            'list' => $this->resource,
            'total' => count($this->resource),
            'page' => 1,
            'page_size' => count($this->resource),
            'total_pages' => 1,
            'has_more' => false,
        ];
    }
}
