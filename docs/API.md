# API 技术文档

## 概述

本文档描述了 Laravel SaaS Ultimate Pro 系统的 API 接口规范。所有 API 接口均遵循 RESTful 设计原则，返回 JSON 格式数据。

### 基础信息

- **基础URL**: `http://laravel-saas-ultimate-pro-full.test.com/api/v1`
- **认证方式**: Bearer Token (JWT)
- **内容类型**: `application/json`
- **字符编码**: `UTF-8`

### 通用响应格式

```json
{
    "code": 200,
    "message": "成功",
    "data": {}
}
```

### 状态码说明

| 状态码 | 说明 |
|--------|------|
| 200 | 成功 |
| 201 | 创建成功 |
| 401 | 未授权 |
| 403 | 禁止访问 |
| 404 | 资源不存在 |
| 422 | 验证失败 |

---

## 认证模块 (AuthController)

处理用户认证相关操作，包括登录、注册、登出、令牌刷新等

**控制器路径**: `App\Http\Controllers\Api\V1\AuthController`

**方法列表**:
- `login(['email', 'password'])` - 用户登录
- `register(['name', 'email', 'password', 'phone'])` - 用户注册
- `logout()` - 用户登出
- `refreshToken()` - 刷新访问令牌
- `me()` - 获取当前用户信息
- `changePassword()` - 修改密码

---

### 用户登录

通过邮箱和密码进行用户认证，返回访问令牌和刷新令牌

**请求方式**: `POST`

**请求路径**: `/api/v1/auth/login`

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| email | string | 是 | 用户邮箱地址 |
| password | string | 是 | 用户密码，最少6个字符 |

**请求示例**:
```json
{
    "email": "admin@example.com",
    "password": "password123"
}
```

**响应示例**:
```json
{
    "code": 200,
    "message": "登录成功",
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "Bearer",
        "expires_in": 3600
    }
}
```

---

### 用户注册

创建新用户账户并返回访问令牌

**请求方式**: `POST`

**请求路径**: `/api/v1/auth/register`

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 是 | 用户名称，只能包含字母和数字 |
| email | string | 是 | 用户邮箱地址，必须唯一 |
| password | string | 是 | 用户密码，最少6个字符 |
| phone | string | 否 | 用户手机号，必须唯一 |

**请求示例**:
```json
{
    "name": "testuser",
    "email": "test@example.com",
    "password": "password123"
}
```

**响应示例**:
```json
{
    "code": 201,
    "message": "成功",
    "data": {
        "user": {
            "id": 1,
            "name": "testuser",
            "email": "test@example.com"
        },
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
    }
}
```

---

### 用户登出

注销当前用户的访问令牌

**请求方式**: `POST`

**请求路径**: `/api/v1/auth/logout`

**认证要求**: 需要 Bearer Token

**响应示例**:
```json
{
    "code": 200,
    "message": "登出成功",
    "data": null
}
```

---

### 刷新访问令牌

使用刷新令牌获取新的访问令牌

**请求方式**: `POST`

**请求路径**: `/api/v1/auth/refresh`

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| refresh_token | string | 是 | 刷新令牌 |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "Bearer",
        "expires_in": 3600
    }
}
```

---

### 获取当前用户信息

返回当前认证用户的详细信息

**请求方式**: `GET`

**请求路径**: `/api/v1/auth/me`

**认证要求**: 需要 Bearer Token

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "id": 1,
        "name": "admin",
        "email": "admin@example.com",
        "roles": ["admin"],
        "permissions": ["user.view", "user.create"]
    }
}
```

---

### 修改密码

更新当前用户的密码

**请求方式**: `POST`

**请求路径**: `/api/v1/auth/change-password`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| old_password | string | 是 | 原密码，最少6个字符 |
| new_password | string | 是 | 新密码，最少6个字符，不能与原密码相同 |

**响应示例**:
```json
{
    "code": 200,
    "message": "密码修改成功",
    "data": null
}
```

---

## 用户模块 (UserController)

处理用户管理相关操作，包括用户的增删改查、启用禁用、角色分配等

**控制器路径**: `App\Http\Controllers\Api\V1\UserController`

**方法列表**:
- `index(Request $request)` - 获取用户列表
- `store(Request $request)` - 创建用户
- `show(int $id)` - 获取用户详情
- `update(Request $request, int $id)` - 更新用户
- `destroy(int $id)` - 删除用户
- `enable(int $id)` - 启用用户
- `disable(int $id)` - 禁用用户
- `assignRoles(Request $request, int $id)` - 分配角色

---

### 获取用户列表

分页获取用户列表，支持按名称、邮箱、状态筛选

**请求方式**: `GET`

**请求路径**: `/api/v1/users`

**认证要求**: 需要 Bearer Token

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| per_page | int | 否 | 每页数量，默认15 |
| name | string | 否 | 按名称筛选 |
| email | string | 否 | 按邮箱筛选 |
| status | int | 否 | 按状态筛选 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "admin",
                "email": "admin@example.com",
                "status": 1,
                "roles": [],
                "department": null,
                "position": null
            }
        ],
        "total": 1,
        "per_page": 15
    }
}
```

---

### 创建用户

创建新用户账户

**请求方式**: `POST`

**请求路径**: `/api/v1/users`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 是 | 用户名称 |
| email | string | 是 | 用户邮箱地址，必须唯一 |
| password | string | 是 | 用户密码，最少6个字符 |
| phone | string | 否 | 用户手机号，必须唯一 |
| department_id | int | 否 | 部门ID |
| position_id | int | 否 | 岗位ID |
| level_id | int | 否 | 职级ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "创建成功",
    "data": {
        "id": 1,
        "name": "testuser",
        "email": "test@example.com"
    }
}
```

---

### 获取用户详情

根据ID获取用户详细信息

**请求方式**: `GET`

**请求路径**: `/api/v1/users/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 用户ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "id": 1,
        "name": "admin",
        "email": "admin@example.com",
        "status": 1,
        "roles": [],
        "department": null,
        "position": null
    }
}
```

---

### 更新用户

更新指定用户的信息

**请求方式**: `PUT` / `PATCH`

**请求路径**: `/api/v1/users/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 用户ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 否 | 用户名称 |
| email | string | 否 | 用户邮箱地址，必须唯一 |
| phone | string | 否 | 用户手机号，必须唯一 |
| department_id | int | 否 | 部门ID |
| position_id | int | 否 | 岗位ID |
| level_id | int | 否 | 职级ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "name": "newname",
        "email": "new@example.com"
    }
}
```

---

### 删除用户

删除指定用户

**请求方式**: `DELETE`

**请求路径**: `/api/v1/users/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 用户ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": null
}
```

---

### 启用用户

将指定用户状态设置为启用

**请求方式**: `POST`

**请求路径**: `/api/v1/users/{id}/enable`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 用户ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "status": 1
    }
}
```

---

### 禁用用户

将指定用户状态设置为禁用

**请求方式**: `POST`

**请求路径**: `/api/v1/users/{id}/disable`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 用户ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "status": 0
    }
}
```

---

### 分配角色

为指定用户分配角色

**请求方式**: `POST`

**请求路径**: `/api/v1/users/{id}/roles`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 用户ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| role_ids | array | 是 | 角色ID数组 |

**请求示例**:
```json
{
    "role_ids": [1, 2, 3]
}
```

**响应示例**:
```json
{
    "code": 200,
    "message": "角色分配成功",
    "data": {
        "id": 1,
        "roles": [
            {"id": 1, "name": "管理员"},
            {"id": 2, "name": "编辑"}
        ]
    }
}
```

---

## 租户模块 (TenantController)

处理租户管理相关操作，包括租户的增删改查、启用禁用、配置管理等

**控制器路径**: `App\Http\Controllers\Api\V1\TenantController`

**方法列表**:
- `index(Request $request)` - 获取租户列表
- `store(Request $request)` - 创建租户
- `show(int $id)` - 获取租户详情
- `update(Request $request, int $id)` - 更新租户
- `destroy(int $id)` - 删除租户
- `enable(int $id)` - 启用租户
- `disable(int $id)` - 禁用租户
- `config(Request $request, int $id)` - 更新租户配置

---

### 获取租户列表

分页获取租户列表，支持按名称、代码、状态筛选

**请求方式**: `GET`

**请求路径**: `/api/v1/tenants`

**认证要求**: 需要 Bearer Token

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| per_page | int | 否 | 每页数量，默认15 |
| name | string | 否 | 按名称筛选 |
| code | string | 否 | 按代码筛选 |
| status | int | 否 | 按状态筛选 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "tenant1",
                "code": "T001",
                "domain": "tenant1.example.com",
                "status": 1
            }
        ],
        "total": 1,
        "per_page": 15
    }
}
```

---

### 创建租户

创建新租户

**请求方式**: `POST`

**请求路径**: `/api/v1/tenants`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 是 | 租户名称 |
| code | string | 是 | 租户代码，必须唯一 |
| domain | string | 否 | 租户域名 |
| database | string | 否 | 租户数据库 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "创建成功",
    "data": {
        "id": 1,
        "name": "tenant1",
        "code": "T001",
        "domain": "tenant1.example.com"
    }
}
```

---

### 获取租户详情

根据ID获取租户详细信息

**请求方式**: `GET`

**请求路径**: `/api/v1/tenants/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 租户ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "id": 1,
        "name": "tenant1",
        "code": "T001",
        "domain": "tenant1.example.com",
        "status": 1,
        "config": {}
    }
}
```

---

### 更新租户

更新指定租户的信息

**请求方式**: `PUT` / `PATCH`

**请求路径**: `/api/v1/tenants/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 租户ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 否 | 租户名称 |
| code | string | 否 | 租户代码，必须唯一 |
| domain | string | 否 | 租户域名 |
| database | string | 否 | 租户数据库 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "name": "newtenant",
        "code": "T002"
    }
}
```

---

### 删除租户

删除指定租户

**请求方式**: `DELETE`

**请求路径**: `/api/v1/tenants/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 租户ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": null
}
```

---

### 启用租户

将指定租户状态设置为启用

**请求方式**: `POST`

**请求路径**: `/api/v1/tenants/{id}/enable`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 租户ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "status": 1
    }
}
```

---

### 禁用租户

将指定租户状态设置为禁用

**请求方式**: `POST`

**请求路径**: `/api/v1/tenants/{id}/disable`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 租户ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "status": 0
    }
}
```

---

## 角色模块 (RoleController)

处理角色管理相关操作，包括角色的增删改查、权限分配、批量删除等

**控制器路径**: `App\Http\Controllers\Api\V1\RoleController`

**方法列表**:
- `index(Request $request)` - 获取角色列表
- `store(Request $request)` - 创建角色
- `show(int $id)` - 获取角色详情
- `update(Request $request, int $id)` - 更新角色
- `destroy(int $id)` - 删除角色
- `assignPermissions(Request $request, int $id)` - 分配权限
- `batchDelete(Request $request)` - 批量删除角色

---

### 获取角色列表

分页获取角色列表，支持按名称、标识、状态筛选

**请求方式**: `GET`

**请求路径**: `/api/v1/roles`

**认证要求**: 需要 Bearer Token

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| per_page | int | 否 | 每页数量，默认15 |
| name | string | 否 | 按名称筛选 |
| slug | string | 否 | 按标识筛选 |
| status | int | 否 | 按状态筛选 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "管理员",
                "slug": "admin",
                "status": 1,
                "permissions": []
            }
        ],
        "total": 1,
        "per_page": 15
    }
}
```

---

### 创建角色

创建新角色

**请求方式**: `POST`

**请求路径**: `/api/v1/roles`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 是 | 角色名称 |
| slug | string | 是 | 角色标识，必须唯一 |
| description | string | 否 | 角色描述 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "创建成功",
    "data": {
        "id": 1,
        "name": "管理员",
        "slug": "admin"
    }
}
```

---

### 获取角色详情

根据ID获取角色详细信息

**请求方式**: `GET`

**请求路径**: `/api/v1/roles/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 角色ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "id": 1,
        "name": "管理员",
        "slug": "admin",
        "status": 1,
        "permissions": []
    }
}
```

---

### 更新角色

更新指定角色的信息

**请求方式**: `PUT` / `PATCH`

**请求路径**: `/api/v1/roles/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 角色ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 否 | 角色名称 |
| slug | string | 否 | 角色标识，必须唯一 |
| description | string | 否 | 角色描述 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "name": "新管理员",
        "slug": "new_admin"
    }
}
```

---

### 删除角色

删除指定角色

**请求方式**: `DELETE`

**请求路径**: `/api/v1/roles/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 角色ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": null
}
```

---

### 分配权限

为指定角色分配权限

**请求方式**: `POST`

**请求路径**: `/api/v1/roles/{id}/permissions`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 角色ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| permission_ids | array | 是 | 权限ID数组 |

**请求示例**:
```json
{
    "permission_ids": [1, 2, 3]
}
```

**响应示例**:
```json
{
    "code": 200,
    "message": "分配成功",
    "data": {
        "id": 1,
        "name": "管理员",
        "permissions": [
            {"id": 1, "name": "用户管理"},
            {"id": 2, "name": "角色管理"}
        ]
    }
}
```

---

### 批量删除角色

批量删除多个角色

**请求方式**: `POST`

**请求路径**: `/api/v1/roles/batch-delete`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| ids | array | 是 | 角色ID数组 |

**请求示例**:
```json
{
    "ids": [1, 2, 3]
}
```

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": {
        "count": 3
    }
}
```

---

## 权限模块 (PermissionController)

处理权限管理相关操作，包括权限的增删改查、批量删除等

**控制器路径**: `App\Http\Controllers\Api\V1\PermissionController`

**方法列表**:
- `index(Request $request)` - 获取权限列表
- `store(Request $request)` - 创建权限
- `show(int $id)` - 获取权限详情
- `update(Request $request, int $id)` - 更新权限
- `destroy(int $id)` - 删除权限
- `batchDelete(Request $request)` - 批量删除权限

---

### 获取权限列表

分页获取权限列表，支持按名称、标识、模块、状态筛选

**请求方式**: `GET`

**请求路径**: `/api/v1/permissions`

**认证要求**: 需要 Bearer Token

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| per_page | int | 否 | 每页数量，默认15 |
| name | string | 否 | 按名称筛选 |
| slug | string | 否 | 按标识筛选 |
| module | string | 否 | 按模块筛选 |
| status | int | 否 | 按状态筛选 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "用户管理",
                "slug": "user.manage",
                "module": "user",
                "status": 1
            }
        ],
        "total": 1,
        "per_page": 15
    }
}
```

---

### 创建权限

创建新权限

**请求方式**: `POST`

**请求路径**: `/api/v1/permissions`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 是 | 权限名称 |
| slug | string | 是 | 权限标识，必须唯一 |
| module | string | 否 | 所属模块 |
| description | string | 否 | 权限描述 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "创建成功",
    "data": {
        "id": 1,
        "name": "用户管理",
        "slug": "user.manage",
        "module": "user"
    }
}
```

---

### 获取权限详情

根据ID获取权限详细信息

**请求方式**: `GET`

**请求路径**: `/api/v1/permissions/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 权限ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "id": 1,
        "name": "用户管理",
        "slug": "user.manage",
        "module": "user",
        "status": 1
    }
}
```

---

### 更新权限

更新指定权限的信息

**请求方式**: `PUT` / `PATCH`

**请求路径**: `/api/v1/permissions/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 权限ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 否 | 权限名称 |
| slug | string | 否 | 权限标识，必须唯一 |
| module | string | 否 | 所属模块 |
| description | string | 否 | 权限描述 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "name": "新用户管理",
        "slug": "user.manage.new"
    }
}
```

---

### 删除权限

删除指定权限

**请求方式**: `DELETE`

**请求路径**: `/api/v1/permissions/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 权限ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": null
}
```

---

### 批量删除权限

批量删除多个权限

**请求方式**: `POST`

**请求路径**: `/api/v1/permissions/batch-delete`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| ids | array | 是 | 权限ID数组 |

**请求示例**:
```json
{
    "ids": [1, 2, 3]
}
```

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": {
        "count": 3
    }
}
```

---

## 部门模块 (DepartmentController)

处理部门管理相关操作，包括部门的增删改查、树形结构、批量删除等

**控制器路径**: `App\Http\Controllers\Api\V1\DepartmentController`

**方法列表**:
- `index(Request $request)` - 获取部门列表
- `tree(Request $request)` - 获取部门树形结构
- `store(Request $request)` - 创建部门
- `show(int $id)` - 获取部门详情
- `update(Request $request, int $id)` - 更新部门
- `destroy(int $id)` - 删除部门
- `batchDelete(Request $request)` - 批量删除部门

---

### 获取部门列表

分页获取部门列表，支持按名称、代码、状态筛选

**请求方式**: `GET`

**请求路径**: `/api/v1/departments`

**认证要求**: 需要 Bearer Token

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| per_page | int | 否 | 每页数量，默认15 |
| name | string | 否 | 按名称筛选 |
| code | string | 否 | 按代码筛选 |
| status | int | 否 | 按状态筛选 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "技术部",
                "code": "TECH",
                "status": 1,
                "parent": null,
                "children": []
            }
        ],
        "total": 1,
        "per_page": 15
    }
}
```

---

### 获取部门树形结构

获取所有部门的树形结构数据

**请求方式**: `GET`

**请求路径**: `/api/v1/departments/tree`

**认证要求**: 需要 Bearer Token

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": [
        {
            "id": 1,
            "name": "总公司",
            "code": "HQ",
            "children": [
                {
                    "id": 2,
                    "name": "技术部",
                    "code": "TECH"
                }
            ]
        }
    ]
}
```

---

### 创建部门

创建新部门

**请求方式**: `POST`

**请求路径**: `/api/v1/departments`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 是 | 部门名称 |
| code | string | 是 | 部门代码，必须唯一 |
| parent_id | int | 否 | 父级部门ID |
| description | string | 否 | 部门描述 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "创建成功",
    "data": {
        "id": 1,
        "name": "技术部",
        "code": "TECH"
    }
}
```

---

### 获取部门详情

根据ID获取部门详细信息

**请求方式**: `GET`

**请求路径**: `/api/v1/departments/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 部门ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "id": 1,
        "name": "技术部",
        "code": "TECH",
        "status": 1,
        "parent": null,
        "children": []
    }
}
```

---

### 更新部门

更新指定部门的信息

**请求方式**: `PUT` / `PATCH`

**请求路径**: `/api/v1/departments/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 部门ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 否 | 部门名称 |
| code | string | 否 | 部门代码，必须唯一 |
| parent_id | int | 否 | 父级部门ID |
| description | string | 否 | 部门描述 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "name": "新技术部",
        "code": "NEW_TECH"
    }
}
```

---

### 删除部门

删除指定部门

**请求方式**: `DELETE`

**请求路径**: `/api/v1/departments/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 部门ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": null
}
```

---

### 批量删除部门

批量删除多个部门

**请求方式**: `POST`

**请求路径**: `/api/v1/departments/batch-delete`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| ids | array | 是 | 部门ID数组 |

**请求示例**:
```json
{
    "ids": [1, 2, 3]
}
```

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": {
        "count": 3
    }
}
```

---

## 岗位模块 (PositionController)

处理岗位管理相关操作，包括岗位的增删改查、批量删除等

**控制器路径**: `App\Http\Controllers\Api\V1\PositionController`

**方法列表**:
- `index(['name', 'code', 'status'])` - 获取岗位列表
- `store(Request $request)` - 创建岗位
- `show(int $id)` - 获取岗位详情
- `update(Request $request, int $id)` - 更新岗位
- `destroy(int $id)` - 删除岗位
- `batchDelete(Request $request)` - 批量删除岗位

---

### 获取岗位列表

分页获取岗位列表，支持按名称、代码、状态筛选

**请求方式**: `GET`

**请求路径**: `/api/v1/positions`

**认证要求**: 需要 Bearer Token

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| per_page | int | 否 | 每页数量，默认15 |
| name | string | 否 | 按名称筛选 |
| code | string | 否 | 按代码筛选 |
| status | int | 否 | 按状态筛选 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "开发工程师",
                "code": "DEV",
                "status": 1
            }
        ],
        "total": 1,
        "per_page": 15
    }
}
```

---

### 创建岗位

创建新岗位

**请求方式**: `POST`

**请求路径**: `/api/v1/positions`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 是 | 岗位名称 |
| code | string | 是 | 岗位代码，必须唯一 |
| description | string | 否 | 岗位描述 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "创建成功",
    "data": {
        "id": 1,
        "name": "开发工程师",
        "code": "DEV"
    }
}
```

---

### 获取岗位详情

根据ID获取岗位详细信息

**请求方式**: `GET`

**请求路径**: `/api/v1/positions/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 岗位ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "id": 1,
        "name": "开发工程师",
        "code": "DEV",
        "status": 1
    }
}
```

---

### 更新岗位

更新指定岗位的信息

**请求方式**: `PUT` / `PATCH`

**请求路径**: `/api/v1/positions/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 岗位ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 否 | 岗位名称 |
| code | string | 否 | 岗位代码，必须唯一 |
| description | string | 否 | 岗位描述 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "name": "高级开发工程师",
        "code": "SENIOR_DEV"
    }
}
```

---

### 删除岗位

删除指定岗位

**请求方式**: `DELETE`

**请求路径**: `/api/v1/positions/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 岗位ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": null
}
```

---

### 批量删除岗位

批量删除多个岗位

**请求方式**: `POST`

**请求路径**: `/api/v1/positions/batch-delete`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| ids | array | 是 | 岗位ID数组 |

**请求示例**:
```json
{
    "ids": [1, 2, 3]
}
```

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": {
        "count": 3
    }
}
```

---

## 职级模块 (LevelController)

处理职级管理相关操作，包括职级的增删改查、批量删除等

**控制器路径**: `App\Http\Controllers\Api\V1\LevelController`

**方法列表**:
- `index(Request $request)` - 获取职级列表
- `store(Request $request)` - 创建职级
- `show(int $id)` - 获取职级详情
- `update(Request $request, int $id)` - 更新职级
- `destroy(int $id)` - 删除职级
- `batchDelete(Request $request)` - 批量删除职级

---

### 获取职级列表

分页获取职级列表，支持按名称、代码、状态筛选

**请求方式**: `GET`

**请求路径**: `/api/v1/levels`

**认证要求**: 需要 Bearer Token

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| per_page | int | 否 | 每页数量，默认15 |
| name | string | 否 | 按名称筛选 |
| code | string | 否 | 按代码筛选 |
| status | int | 否 | 按状态筛选 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "高级工程师",
                "code": "SENIOR",
                "status": 1
            }
        ],
        "total": 1,
        "per_page": 15
    }
}
```

---

### 创建职级

创建新职级

**请求方式**: `POST`

**请求路径**: `/api/v1/levels`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 是 | 职级名称 |
| code | string | 是 | 职级代码，必须唯一 |
| description | string | 否 | 职级描述 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "创建成功",
    "data": {
        "id": 1,
        "name": "高级工程师",
        "code": "SENIOR"
    }
}
```

---

### 获取职级详情

根据ID获取职级详细信息

**请求方式**: `GET`

**请求路径**: `/api/v1/levels/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 职级ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "id": 1,
        "name": "高级工程师",
        "code": "SENIOR",
        "status": 1
    }
}
```

---

### 更新职级

更新指定职级的信息

**请求方式**: `PUT` / `PATCH`

**请求路径**: `/api/v1/levels/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 职级ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 否 | 职级名称 |
| code | string | 否 | 职级代码，必须唯一 |
| description | string | 否 | 职级描述 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "name": "资深工程师",
        "code": "PRINCIPAL"
    }
}
```

---

### 删除职级

删除指定职级

**请求方式**: `DELETE`

**请求路径**: `/api/v1/levels/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 职级ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": null
}
```

---

### 批量删除职级

批量删除多个职级

**请求方式**: `POST`

**请求路径**: `/api/v1/levels/batch-delete`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| ids | array | 是 | 职级ID数组 |

**请求示例**:
```json
{
    "ids": [1, 2, 3]
}
```

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": {
        "count": 3
    }
}
```

---

## 菜单模块 (MenuController)

处理菜单管理相关操作，包括菜单的增删改查、树形结构、用户菜单、批量删除等

**控制器路径**: `App\Http\Controllers\Api\V1\MenuController`

**方法列表**:
- `index(Request $request)` - 获取菜单列表
- `tree(Request $request)` - 获取菜单树形结构
- `userMenus(Request $request)` - 获取当前用户菜单
- `store(Request $request)` - 创建菜单
- `show(int $id)` - 获取菜单详情
- `update(Request $request, int $id)` - 更新菜单
- `destroy(int $id)` - 删除菜单
- `batchDelete(Request $request)` - 批量删除菜单

---

### 获取菜单列表

分页获取菜单列表，支持按名称、标识、状态筛选

**请求方式**: `GET`

**请求路径**: `/api/v1/menus`

**认证要求**: 需要 Bearer Token

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| per_page | int | 否 | 每页数量，默认15 |
| name | string | 否 | 按名称筛选 |
| slug | string | 否 | 按标识筛选 |
| status | int | 否 | 按状态筛选 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "用户管理",
                "slug": "user",
                "status": 1,
                "parent": null,
                "children": []
            }
        ],
        "total": 1,
        "per_page": 15
    }
}
```

---

### 获取菜单树形结构

获取所有菜单的树形结构数据

**请求方式**: `GET`

**请求路径**: `/api/v1/menus/tree`

**认证要求**: 需要 Bearer Token

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": [
        {
            "id": 1,
            "name": "系统管理",
            "slug": "system",
            "children": [
                {
                    "id": 2,
                    "name": "用户管理",
                    "slug": "user"
                }
            ]
        }
    ]
}
```

---

### 获取当前用户菜单

获取当前登录用户有权访问的菜单列表

**请求方式**: `GET`

**请求路径**: `/api/v1/menus/user`

**认证要求**: 需要 Bearer Token

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": [
        {
            "id": 1,
            "name": "系统管理",
            "slug": "system",
            "children": [
                {
                    "id": 2,
                    "name": "用户管理",
                    "slug": "user"
                }
            ]
        }
    ]
}
```

---

### 创建菜单

创建新菜单

**请求方式**: `POST`

**请求路径**: `/api/v1/menus`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 是 | 菜单名称 |
| slug | string | 是 | 菜单标识，必须唯一 |
| parent_id | int | 否 | 父级菜单ID |
| icon | string | 否 | 菜单图标 |
| path | string | 否 | 路由路径 |
| component | string | 否 | 组件路径 |
| sort | int | 否 | 排序 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "创建成功",
    "data": {
        "id": 1,
        "name": "用户管理",
        "slug": "user"
    }
}
```

---

### 获取菜单详情

根据ID获取菜单详细信息

**请求方式**: `GET`

**请求路径**: `/api/v1/menus/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 菜单ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "id": 1,
        "name": "用户管理",
        "slug": "user",
        "status": 1,
        "parent": null,
        "children": []
    }
}
```

---

### 更新菜单

更新指定菜单的信息

**请求方式**: `PUT` / `PATCH`

**请求路径**: `/api/v1/menus/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 菜单ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 否 | 菜单名称 |
| slug | string | 否 | 菜单标识，必须唯一 |
| parent_id | int | 否 | 父级菜单ID |
| icon | string | 否 | 菜单图标 |
| path | string | 否 | 路由路径 |
| component | string | 否 | 组件路径 |
| sort | int | 否 | 排序 |
| status | int | 否 | 状态 (0:禁用, 1:启用) |

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 1,
        "name": "新用户管理",
        "slug": "new_user"
    }
}
```

---

### 删除菜单

删除指定菜单

**请求方式**: `DELETE`

**请求路径**: `/api/v1/menus/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 菜单ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": null
}
```

---

### 批量删除菜单

批量删除多个菜单

**请求方式**: `POST`

**请求路径**: `/api/v1/menus/batch-delete`

**认证要求**: 需要 Bearer Token

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| ids | array | 是 | 菜单ID数组 |

**请求示例**:
```json
{
    "ids": [1, 2, 3]
}
```

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": {
        "count": 3
    }
}
```

---

## 设备模块 (DeviceController)

处理设备管理相关操作，包括设备列表、设备详情、设备删除、设备登出等

**控制器路径**: `App\Http\Controllers\Api\V1\DeviceController`

**方法列表**:
- `index(Request $request)` - 获取设备列表
- `show(int $id)` - 获取设备详情
- `destroy(int $id)` - 删除设备
- `logout(int $id)` - 登出指定设备
- `logoutAll(Request $request)` - 登出所有设备

---

### 获取设备列表

分页获取设备列表，支持按设备ID、设备名称、设备类型、状态筛选

**请求方式**: `GET`

**请求路径**: `/api/v1/devices`

**认证要求**: 需要 Bearer Token

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| per_page | int | 否 | 每页数量，默认15 |
| device_id | string | 否 | 按设备ID筛选 |
| device_name | string | 否 | 按设备名称筛选 |
| device_type | string | 否 | 按设备类型筛选 |
| status | int | 否 | 按状态筛选 (0:离线, 1:在线) |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "device_id": "device-001",
                "device_name": "iPhone 15",
                "device_type": "mobile",
                "status": 1,
                "user": {
                    "id": 1,
                    "name": "admin"
                }
            }
        ],
        "total": 1,
        "per_page": 15
    }
}
```

---

### 获取设备详情

根据ID获取设备详细信息

**请求方式**: `GET`

**请求路径**: `/api/v1/devices/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 设备ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "id": 1,
        "device_id": "device-001",
        "device_name": "iPhone 15",
        "device_type": "mobile",
        "status": 1,
        "user": {
            "id": 1,
            "name": "admin"
        }
    }
}
```

---

### 删除设备

删除指定设备记录

**请求方式**: `DELETE`

**请求路径**: `/api/v1/devices/{id}`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 设备ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "删除成功",
    "data": null
}
```

---

### 登出指定设备

使指定设备的登录令牌失效，强制登出该设备

**请求方式**: `POST`

**请求路径**: `/api/v1/devices/{id}/logout`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 设备ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": null
}
```

---

### 登出所有设备

使当前用户所有设备的登录令牌失效，强制登出所有设备

**请求方式**: `POST`

**请求路径**: `/api/v1/devices/logout-all`

**认证要求**: 需要 Bearer Token

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "count": 5
    }
}
```

---

## 租户限流模块 (TenantRateLimitController)

处理租户限流配置相关操作，包括获取配置、设置配置、获取状态、重置限流等

**控制器路径**: `App\Http\Controllers\Api\V1\TenantRateLimitController`

**方法列表**:
- `getConfig(int $tenantId)` - 获取租户限流配置
- `setConfig(Request $request, int $tenantId)` - 设置租户限流配置
- `getStatus(int $tenantId)` - 获取租户限流状态
- `reset(Request $request, int $tenantId)` - 重置租户限流

---

### 获取租户限流配置

获取指定租户的限流配置信息

**请求方式**: `GET`

**请求路径**: `/api/v1/tenants/{tenantId}/rate-limit/config`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| tenantId | int | 是 | 租户ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "requests_per_minute": 60,
        "requests_per_hour": 1000,
        "requests_per_day": 10000
    }
}
```

---

### 设置租户限流配置

设置指定租户的限流配置

**请求方式**: `POST`

**请求路径**: `/api/v1/tenants/{tenantId}/rate-limit/config`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| tenantId | int | 是 | 租户ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| requests_per_minute | int | 否 | 每分钟请求数限制 (1-10000) |
| requests_per_hour | int | 否 | 每小时请求数限制 (1-100000) |
| requests_per_day | int | 否 | 每天请求数限制 (1-1000000) |

**请求示例**:
```json
{
    "requests_per_minute": 60,
    "requests_per_hour": 1000,
    "requests_per_day": 10000
}
```

**响应示例**:
```json
{
    "code": 200,
    "message": "更新成功",
    "data": null
}
```

---

### 获取租户限流状态

获取指定租户当前的限流状态信息

**请求方式**: `GET`

**请求路径**: `/api/v1/tenants/{tenantId}/rate-limit/status`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| tenantId | int | 是 | 租户ID |

**响应示例**:
```json
{
    "code": 200,
    "message": "成功",
    "data": {
        "current_minute": 10,
        "current_hour": 150,
        "current_day": 1200,
        "limit_reached": false
    }
}
```

---

### 重置租户限流

重置指定租户的限流计数器

**请求方式**: `POST`

**请求路径**: `/api/v1/tenants/{tenantId}/rate-limit/reset`

**认证要求**: 需要 Bearer Token

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| tenantId | int | 是 | 租户ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| endpoint | string | 否 | 要重置的端点，不传则重置所有 |

**请求示例**:
```json
{
    "endpoint": "/api/v1/users"
}
```

**响应示例**:
```json
{
    "code": 200,
    "message": "重置成功",
    "data": null
}
```

---

## 错误响应

### 验证失败 (422)

```json
{
    "code": 422,
    "message": "验证失败",
    "data": {
        "errors": {
            "email": ["邮箱 不能为空"],
            "password": ["密码 不能为空"]
        }
    }
}
```

### 未授权 (401)

```json
{
    "code": 401,
    "message": "未授权访问",
    "data": null
}
```

### 禁止访问 (403)

```json
{
    "code": 403,
    "message": "禁止访问",
    "data": null
}
```

### 资源不存在 (404)

```json
{
    "code": 404,
    "message": "资源不存在",
    "data": null
}
```

---

## 多语言支持

API 支持多语言错误消息，通过请求头 `Accept-Language` 指定语言：

- `zh-CN` - 简体中文（默认）
- `en` - 英文
- `ko` - 韩文

**请求示例**:
```
GET /api/v1/users
Accept-Language: en
Authorization: Bearer {token}
```

---

## 版本历史

| 版本 | 日期 | 说明 |
|------|------|------|
| 1.0.0 | 2026-03-27 | 初始版本 |
