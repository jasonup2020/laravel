<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 租户资源
 * 
 * 格式化租户数据输出
 */
class TenantResource extends JsonResource
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
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'domain' => $this->domain,
            'logo' => $this->logo,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'address' => $this->address,
            'config' => $this->config,
            'expire_at' => $this->expire_at?->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'status_text' => $this->getStatusText(),
            'is_expired' => $this->isExpired(),
            'is_enabled' => $this->isEnabled(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 获取状态文本
     *
     * @return string
     */
    protected function getStatusText(): string
    {
        $statusMap = [
            0 => '禁用',
            1 => '启用',
        ];

        return $statusMap[$this->status] ?? '未知';
    }
}
