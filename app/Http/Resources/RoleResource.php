<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 角色资源
 */
class RoleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'sort' => $this->sort,
            'status' => $this->status,
            'status_text' => $this->status === 1 ? '启用' : '禁用',
            'permissions' => $this->whenLoaded('permissions', fn() => PermissionResource::collection($this->permissions)),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
