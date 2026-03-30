# API控制器验证类完善方案

## 分析时间
- **分析日期**: 2026-03-27
- **分析人员**: CodeArts Agent

---

## 📊 控制器方法分析

### 1. AuthController（认证控制器）

| 方法 | 参数 | 当前验证状态 | 需要创建的验证类 |
|------|------|-------------|----------------|
| login(Request $request) | email, password | ❌ 无验证 | LoginRequest |
| register(Request $request) | name, email, password, phone | ✅ 已有验证 | - |
| logout(Request $request) | - | ✅ 无需验证 | - |
| refreshToken(Request $request) | refresh_token | ⚠️ 简单验证 | RefreshTokenRequest |
| me() | - | ✅ 无需验证 | - |
| changePassword(Request $request) | old_password, new_password | ❌ 无验证 | ChangePasswordRequest |

---

### 2. UserController（用户控制器）

| 方法 | 参数 | 当前验证状态 | 需要创建的验证类 |
|------|------|-------------|----------------|
| index(Request $request) | name, email, status, per_page | ⚠️ 简单过滤 | UserIndexRequest |
| store(StoreUserRequest $request) | - | ✅ 已有验证 | - |
| show(int $id) | - | ✅ 无需验证 | - |
| update(UpdateUserRequest $request) | - | ✅ 已有验证 | - |
| destroy(int $id) | - | ✅ 无需验证 | - |
| enable(int $id) | - | ✅ 无需验证 | - |
| disable(int $id) | - | ✅ 无需验证 | - |
| assignRoles(Request $request, int $id) | role_ids | ❌ 无验证 | AssignRolesRequest |

---

### 3. RoleController（角色控制器）

| 方法 | 参数 | 当前验证状态 | 需要创建的验证类 |
|------|------|-------------|----------------|
| index(Request $request) | name, slug, status, per_page | ⚠️ 简单过滤 | RoleIndexRequest |
| store(StoreRoleRequest $request) | - | ✅ 已有验证 | - |
| show(int $id) | - | ✅ 无需验证 | - |
| update(Request $request, int $id) | name, slug, description, status | ❌ 无验证 | UpdateRoleRequest |
| destroy(int $id) | - | ✅ 无需验证 | - |
| assignPermissions(Request $request, int $id) | permission_ids | ❌ 无验证 | AssignPermissionsRequest |
| batchDelete(Request $request) | ids | ❌ 无验证 | BatchDeleteRequest |

---

### 4. PermissionController（权限控制器）

| 方法 | 参数 | 当前验证状态 | 需要创建的验证类 |
|------|------|-------------|----------------|
| index(Request $request) | name, slug, status, per_page | ⚠️ 简单过滤 | PermissionIndexRequest |
| store(PermissionRequest $request) | - | ✅ 已有验证 | - |
| show(int $id) | - | ✅ 无需验证 | - |
| update(PermissionRequest $request) | - | ✅ 已有验证 | - |
| destroy(int $id) | - | ✅ 无需验证 | - |
| batchDelete(Request $request) | ids | ❌ 无验证 | BatchDeleteRequest |

---

### 5. MenuController（菜单控制器）

| 方法 | 参数 | 当前验证状态 | 需要创建的验证类 |
|------|------|-------------|----------------|
| index(Request $request) | name, status, per_page | ⚠️ 简单过滤 | MenuIndexRequest |
| tree(Request $request) | - | ✅ 无需验证 | - |
| userMenus(Request $request) | - | ✅ 无需验证 | - |
| store(MenuRequest $request) | - | ✅ 已有验证 | - |
| show(int $id) | - | ✅ 无需验证 | - |
| update(MenuRequest $request) | - | ✅ 已有验证 | - |
| destroy(int $id) | - | ✅ 无需验证 | - |
| batchDelete(Request $request) | ids | ❌ 无验证 | BatchDeleteRequest |

---

### 6. DepartmentController（部门控制器）

| 方法 | 参数 | 当前验证状态 | 需要创建的验证类 |
|------|------|-------------|----------------|
| index(Request $request) | name, status, per_page | ⚠️ 简单过滤 | DepartmentIndexRequest |
| tree(Request $request) | - | ✅ 无需验证 | - |
| store(StoreDepartmentRequest $request) | - | ✅ 已有验证 | - |
| show(int $id) | - | ✅ 无需验证 | - |
| update(DepartmentRequest $request) | - | ✅ 已有验证 | - |
| destroy(int $id) | - | ✅ 无需验证 | - |
| batchDelete(Request $request) | ids | ❌ 无验证 | BatchDeleteRequest |

---

### 7. PositionController（岗位控制器）

| 方法 | 参数 | 当前验证状态 | 需要创建的验证类 |
|------|------|-------------|----------------|
| index(Request $request) | name, status, per_page | ⚠️ 简单过滤 | PositionIndexRequest |
| store(StorePositionRequest $request) | - | ✅ 已有验证 | - |
| show(int $id) | - | ✅ 无需验证 | - |
| update(PositionRequest $request) | - | ✅ 已有验证 | - |
| destroy(int $id) | - | ✅ 无需验证 | - |
| batchDelete(Request $request) | ids | ❌ 无验证 | BatchDeleteRequest |

---

### 8. LevelController（职级控制器）

| 方法 | 参数 | 当前验证状态 | 需要创建的验证类 |
|------|------|-------------|----------------|
| index(Request $request) | name, status, per_page | ⚠️ 简单过滤 | LevelIndexRequest |
| store(StoreLevelRequest $request) | - | ✅ 已有验证 | - |
| show(int $id) | - | ✅ 无需验证 | - |
| update(LevelRequest $request) | - | ✅ 已有验证 | - |
| destroy(int $id) | - | ✅ 无需验证 | - |
| batchDelete(Request $request) | ids | ❌ 无验证 | BatchDeleteRequest |

---

### 9. DeviceController（设备控制器）

| 方法 | 参数 | 当前验证状态 | 需要创建的验证类 |
|------|------|-------------|----------------|
| index(Request $request) | user_id, status, per_page | ⚠️ 简单过滤 | DeviceIndexRequest |
| show(int $id) | - | ✅ 无需验证 | - |
| destroy(int $id) | - | ✅ 无需验证 | - |
| logout(int $id) | - | ✅ 无需验证 | - |
| logoutAll(Request $request) | - | ✅ 无需验证 | - |

---

### 10. TenantController（租户控制器）

| 方法 | 参数 | 当前验证状态 | 需要创建的验证类 |
|------|------|-------------|----------------|
| index(Request $request) | name, status, per_page | ⚠️ 简单过滤 | TenantIndexRequest |
| store(Request $request) | name, slug, domain, status | ❌ 无验证 | StoreTenantRequest |
| show(int $id) | - | ✅ 无需验证 | - |
| update(Request $request, int $id) | name, slug, domain, status | ❌ 无验证 | UpdateTenantRequest |
| destroy(int $id) | - | ✅ 无需验证 | - |
| enable(int $id) | - | ✅ 无需验证 | - |
| disable(int $id) | - | ✅ 无需验证 | - |
| getConfig(int $id) | - | ✅ 无需验证 | - |
| setConfig(Request $request, int $id) | config | ❌ 无验证 | TenantConfigRequest |

---

### 11. TenantRateLimitController（租户限流控制器）

| 方法 | 参数 | 当前验证状态 | 需要创建的验证类 |
|------|------|-------------|----------------|
| getConfig(int $tenantId) | - | ✅ 无需验证 | - |
| setConfig(Request $request, int $tenantId) | rate_limits | ❌ 无验证 | RateLimitConfigRequest |
| getStatus(int $tenantId) | - | ✅ 无需验证 | - |
| reset(Request $request, int $tenantId) | endpoint | ❌ 无验证 | RateLimitResetRequest |

---

## 📝 需要创建的验证类汇总

### 通用验证类（可复用）
1. **BatchDeleteRequest** - 批量删除验证
   - ids: required|array
   - ids.*: integer|exists:table,id

### 认证相关验证类
2. **LoginRequest** - 登录验证
   - email: required|email
   - password: required|string|min:6

3. **RefreshTokenRequest** - 刷新令牌验证
   - refresh_token: required|string

4. **ChangePasswordRequest** - 修改密码验证
   - old_password: required|string
   - new_password: required|string|min:6|different:old_password

### 用户相关验证类
5. **UserIndexRequest** - 用户列表查询验证
   - name: nullable|string
   - email: nullable|email
   - status: nullable|integer|in:0,1
   - per_page: nullable|integer|min:1|max:100

6. **AssignRolesRequest** - 分配角色验证
   - role_ids: required|array
   - role_ids.*: integer|exists:roles,id

### 角色相关验证类
7. **RoleIndexRequest** - 角色列表查询验证
   - name: nullable|string
   - slug: nullable|string
   - status: nullable|integer|in:0,1
   - per_page: nullable|integer|min:1|max:100

8. **UpdateRoleRequest** - 更新角色验证
   - name: required|string|max:255
   - slug: required|string|max:100|unique:roles,slug,{id}
   - description: nullable|string
   - status: nullable|integer|in:0,1

9. **AssignPermissionsRequest** - 分配权限验证
   - permission_ids: required|array
   - permission_ids.*: integer|exists:permissions,id

### 权限相关验证类
10. **PermissionIndexRequest** - 权限列表查询验证
    - name: nullable|string
    - slug: nullable|string
    - status: nullable|integer|in:0,1
    - per_page: nullable|integer|min:1|max:100

### 菜单相关验证类
11. **MenuIndexRequest** - 菜单列表查询验证
    - name: nullable|string
    - status: nullable|integer|in:0,1
    - per_page: nullable|integer|min:1|max:100

### 部门相关验证类
12. **DepartmentIndexRequest** - 部门列表查询验证
    - name: nullable|string
    - status: nullable|integer|in:0,1
    - per_page: nullable|integer|min:1|max:100

### 岗位相关验证类
13. **PositionIndexRequest** - 岗位列表查询验证
    - name: nullable|string
    - status: nullable|integer|in:0,1
    - per_page: nullable|integer|min:1|max:100

### 职级相关验证类
14. **LevelIndexRequest** - 职级列表查询验证
    - name: nullable|string
    - status: nullable|integer|in:0,1
    - per_page: nullable|integer|min:1|max:100

### 设备相关验证类
15. **DeviceIndexRequest** - 设备列表查询验证
    - user_id: nullable|integer|exists:users,id
    - status: nullable|integer|in:0,1
    - per_page: nullable|integer|min:1|max:100

### 租户相关验证类
16. **TenantIndexRequest** - 租户列表查询验证
    - name: nullable|string
    - status: nullable|integer|in:0,1
    - per_page: nullable|integer|min:1|max:100

17. **StoreTenantRequest** - 创建租户验证
    - name: required|string|max:255
    - slug: required|string|max:100|unique:tenants
    - domain: nullable|string|max:255|unique:tenants
    - status: nullable|integer|in:0,1

18. **UpdateTenantRequest** - 更新租户验证
    - name: required|string|max:255
    - slug: required|string|max:100|unique:tenants,slug,{id}
    - domain: nullable|string|max:255|unique:tenants,domain,{id}
    - status: nullable|integer|in:0,1

19. **TenantConfigRequest** - 租户配置验证
    - config: required|array

### 限流相关验证类
20. **RateLimitConfigRequest** - 限流配置验证
    - rate_limits: required|array
    - rate_limits.*.endpoint: required|string
    - rate_limits.*.max_attempts: required|integer|min:1
    - rate_limits.*.decay_minutes: required|integer|min:1

21. **RateLimitResetRequest** - 限流重置验证
    - endpoint: nullable|string

---

## 📊 统计信息

- **总控制器数**: 11个
- **总方法数**: 78个
- **已有验证的方法**: 32个
- **无需验证的方法**: 25个
- **需要添加验证的方法**: 21个
- **需要创建的验证类**: 21个

---

## 🎯 实施计划

### 阶段1: 创建通用验证类
1. BatchDeleteRequest

### 阶段2: 创建认证验证类
2. LoginRequest
3. RefreshTokenRequest
4. ChangePasswordRequest

### 阶段3: 创建用户验证类
5. UserIndexRequest
6. AssignRolesRequest

### 阶段4: 创建角色验证类
7. RoleIndexRequest
8. UpdateRoleRequest
9. AssignPermissionsRequest

### 阶段5: 创建其他验证类
10-21. 其他模块的验证类

### 阶段6: 应用验证类到控制器
修改控制器方法，使用FormRequest验证

### 阶段7: 测试验证功能
测试所有验证规则是否生效

---

**分析完成时间**: 2026-03-27
**分析人员**: CodeArts Agent
