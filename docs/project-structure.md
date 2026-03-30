# 项目结构

```
app/
├── Console/
│   └── Commands/
│       ├── AiCrudMake.php                  # 自动创建CRUD命令
│       └── Kernel.php                      # 命令行工具核心类
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── AuthController.php          # 认证控制器，处理登录、注册等认证相关操作
│   │       ├── UserController.php          # 用户控制器，处理用户相关操作，如获取用户列表、更新用户信息等
│   │       ├── RoleController.php          # 角色控制器，处理角色相关操作，如获取角色列表、更新角色权限等
│   │       ├── PermissionController.php    # 权限控制器，处理权限相关操作，如获取权限列表、更新权限等
│   │       ├── TenantController.php        # 租户控制器，处理租户相关操作，如获取租户列表、更新租户信息等
│   │       ├── DeviceController.php        # 设备控制器，处理设备相关操作，如获取设备列表、更新设备令牌等
│   │       ├── DepartmentController.php    # 部门控制器，处理部门相关操作，如获取部门列表、创建部门等
│   │       ├── LevelController.php         # 职级控制器，处理职级相关操作，如获取职级列表、创建职级等
│   │       └── PositionController.php      # 岗位控制器，处理岗位相关操作，如获取岗位列表、分配权限等
│   ├── Middleware/
│   │   ├── Authenticate.php              # 认证中间件，用于验证用户身份
│   │   ├── CheckRole.php                 # 角色中间件，用于检查用户角色
│   │   ├── CheckPermission.php           # 权限中间件，用于检查用户权限
│   │   ├── JwtMiddleware.php             # JWT中间件，用于验证JWT令牌
│   │   ├── PermissionMiddleware.php      # 权限验证中间件，用于检查用户权限
│   │   ├── RoleMiddleware.php            # 角色验证中间件，用于检查用户角色
│   │   ├── DepartmentAccessMiddleware.php # 部门访问控制中间件，用于检查部门访问权限
│   │   └── PositionAccessMiddleware.php  # 岗位访问控制中间件，用于检查岗位访问权限
│   └── Requests/
│       ├── RegisterRequest.php          # 注册请求，用于验证用户注册信息
│       ├── LoginRequest.php             # 登录请求，用于验证用户登录信息
│       ├── UpdateUserRequest.php        # 更新用户请求，用于验证用户信息更新
│       ├── StoreUserRequest.php         # 存储用户请求，用于验证用户信息存储
│       ├── StoreRoleRequest.php         # 存储角色请求，用于验证角色信息存储
│       ├── StorePermissionRequest.php   # 存储权限请求，用于验证权限信息存储
│       ├── StoreTenantRequest.php       # 存储租户请求，用于验证租户信息存储
│       ├── StoreDepartmentRequest.php   # 存储部门请求，用于验证部门信息存储
│       ├── UpdateDepartmentRequest.php  # 更新部门请求，用于验证部门信息更新
│       ├── StoreLevelRequest.php        # 存储职级请求，用于验证职级信息存储
│       ├── UpdateLevelRequest.php       # 更新职级请求，用于验证职级信息更新
│       ├── StorePositionRequest.php     # 存储岗位请求，用于验证岗位信息存储
│       ├── UpdatePositionRequest.php    # 更新岗位请求，用于验证岗位信息更新
│       └── AssignPermissionsToPositionRequest.php # 分配权限到岗位请求，用于验证岗位权限分配
├── Models/
│   ├── User.php                        # 用户模型，用于存储用户信息
│   ├── Tenant.php                      # 租户模型，用于存储租户信息
│   ├── Role.php                        # 角色模型，用于存储角色信息
│   ├── Permission.php                  # 权限模型，用于存储权限信息
│   ├── DeviceToken.php                 # 设备令牌模型，用于存储设备令牌信息
│   ├── TokenBlacklist.php              # 令牌黑名单模型，用于存储令牌登出或失效的令牌
│   ├── ApiLog.php                      # API日志模型，用于存储API请求日志
│   ├── Department.php                  # 部门模型，用于存储部门信息
│   ├── Level.php                       # 职级模型，用于存储职级信息
│   ├── Position.php                    # 岗位模型，用于存储岗位信息
│   └── PositionPermission.php          # 岗位权限关联模型，用于存储岗位与权限的关联关系
├── Models/Traits/
│   └── BaseModelTrait.php              # 基础模型Trait，提供通用的CRUD操作和审计功能
├── Providers/
│   └── AppServiceProvider.php          # 应用服务提供器，用于注册应用级服务
├── Services/
│   ├── AuthService.php                 # 认证服务，用于处理用户认证相关操作
│   ├── RBACService.php                 # RBAC服务，用于处理角色权限相关操作
│   ├── TenantService.php               # 租户服务，用于处理租户相关操作
│   ├── DeviceService.php               # 设备服务，用于处理设备相关操作
│   ├── ApiLogService.php               # API日志服务，用于处理API日志相关操作
│   └── FilterBuilder.php               # 过滤器构建器，用于构建查询过滤器
├── Support/
│   └── JwtManager.php                  # JWT管理器，用于处理JWT相关操作
└── Helpers/
    ├── common.php                      # 通用助手函数，用于处理通用任务
    └── function.php                    # 函数助手函数，用于处理函数相关操作
```
