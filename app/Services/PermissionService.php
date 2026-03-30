<?php

namespace App\Services;

use App\Models\Permission;

/**
 * 权限服务
 */
class PermissionService extends BaseService
{
    protected string $modelClass = Permission::class;
}
