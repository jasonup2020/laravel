<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'tenant_id' => $this->tenant_id,
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            'device_type' => $this->device_type,
            'platform' => $this->platform,
            'browser' => $this->browser,
            'os' => $this->os,
            'ip' => $this->ip,
            'status' => $this->status,
            'last_active_at' => $this->last_active_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
