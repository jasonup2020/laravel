<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\PositionController;
use App\Http\Controllers\Api\V1\LevelController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\TenantRateLimitController;
use App\Http\Middleware\JwtMiddleware;
use App\Http\Middleware\TenantMiddleware;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// API版本前缀
Route::prefix('v1')->group(function () {

    // ==================== 公开接口 ====================
    
    // 认证相关（无需登录）
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
        Route::post('refresh', [AuthController::class, 'refreshToken']);
    });

    // ==================== 需要认证的接口 ====================
    Route::middleware([JwtMiddleware::class, TenantMiddleware::class])->group(function () {

        // 认证相关
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::put('password', [AuthController::class, 'changePassword']);
        });

        // 用户管理
        Route::apiResource('users', UserController::class);
        Route::put('users/{id}/enable', [UserController::class, 'enable']);
        Route::put('users/{id}/disable', [UserController::class, 'disable']);
        Route::post('users/{id}/roles', [UserController::class, 'assignRoles']);

        // 租户管理（超管权限）
        Route::middleware('permission:tenant')->group(function () {
            Route::apiResource('tenants', TenantController::class);
            Route::put('tenants/{id}/enable', [TenantController::class, 'enable']);
            Route::put('tenants/{id}/disable', [TenantController::class, 'disable']);
            Route::get('tenants/{id}/config', [TenantController::class, 'getConfig']);
            Route::put('tenants/{id}/config', [TenantController::class, 'setConfig']);
            
            // 租户限流管理
            Route::get('tenants/{id}/rate-limit', [TenantRateLimitController::class, 'getConfig']);
            Route::put('tenants/{id}/rate-limit', [TenantRateLimitController::class, 'setConfig']);
            Route::get('tenants/{id}/rate-limit/status', [TenantRateLimitController::class, 'getStatus']);
            Route::post('tenants/{id}/rate-limit/reset', [TenantRateLimitController::class, 'reset']);
        });

        // 角色管理
        Route::apiResource('roles', RoleController::class);
        Route::post('roles/{id}/assign-permissions', [RoleController::class, 'assignPermissions']);
        Route::post('roles/batch-delete', [RoleController::class, 'batchDelete']);

        // 权限管理
        Route::apiResource('permissions', PermissionController::class);
        Route::post('permissions/batch-delete', [PermissionController::class, 'batchDelete']);

        // 部门管理
        Route::get('departments/tree', [DepartmentController::class, 'tree']);
        Route::post('departments/batch-delete', [DepartmentController::class, 'batchDelete']);
        Route::apiResource('departments', DepartmentController::class);

        // 岗位管理
        Route::post('positions/batch-delete', [PositionController::class, 'batchDelete']);
        Route::apiResource('positions', PositionController::class);

        // 职级管理
        Route::post('levels/batch-delete', [LevelController::class, 'batchDelete']);
        Route::apiResource('levels', LevelController::class);

        // 菜单管理
        Route::get('menus/tree', [MenuController::class, 'tree']);
        Route::get('menus/user-menus', [MenuController::class, 'userMenus']);
        Route::post('menus/batch-delete', [MenuController::class, 'batchDelete']);
        Route::apiResource('menus', MenuController::class);

        // 设备管理
        Route::post('devices/{id}/logout', [DeviceController::class, 'logout']);
        Route::post('devices/logout-all', [DeviceController::class, 'logoutAll']);
        Route::apiResource('devices', DeviceController::class);
    });
});

// ==================== 健康检查 ====================
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toDateTimeString(),
    ]);
});
