<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 用户资源
 */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
            'gender' => $this->gender,
            'gender_text' => $this->getGenderText(),
            'birthday' => $this->birthday?->format('Y-m-d'),
            'signature' => $this->signature,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'level_id' => $this->level_id,
            'department' => $this->whenLoaded('department', fn() => new DepartmentResource($this->department)),
            'position' => $this->whenLoaded('position', fn() => new PositionResource($this->position)),
            'level' => $this->whenLoaded('level', fn() => new LevelResource($this->level)),
            'roles' => $this->whenLoaded('roles', fn() => RoleResource::collection($this->roles)),
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'last_login_ip' => $this->last_login_ip,
            'login_count' => $this->login_count,
            'status' => $this->status,
            'status_text' => $this->getStatusText(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function getGenderText(): string
    {
        $map = [0 => '未知', 1 => '男', 2 => '女'];
        return $map[$this->gender] ?? '未知';
    }

    protected function getStatusText(): string
    {
        $map = [0 => '禁用', 1 => '启用'];
        return $map[$this->status] ?? '未知';
    }
}
