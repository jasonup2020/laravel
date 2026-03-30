<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 部门资源
 */
class DepartmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'code' => $this->code,
            'parent_id' => $this->parent_id,
            'leader' => $this->leader,
            'phone' => $this->phone,
            'email' => $this->email,
            'sort' => $this->sort,
            'status' => $this->status,
            'status_text' => $this->status === 1 ? '启用' : '禁用',
            'children' => $this->whenLoaded('children', fn() => DepartmentResource::collection($this->children)),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
