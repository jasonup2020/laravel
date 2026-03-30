<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 权限资源
 */
class PermissionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type,
            'type_text' => $this->getTypeText(),
            'parent_id' => $this->parent_id,
            'path' => $this->path,
            'component' => $this->component,
            'icon' => $this->icon,
            'sort' => $this->sort,
            'status' => $this->status,
            'status_text' => $this->status === 1 ? '启用' : '禁用',
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function getTypeText(): string
    {
        $map = [1 => '菜单', 2 => '按钮', 3 => '接口'];
        return $map[$this->type] ?? '未知';
    }
}
