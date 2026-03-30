# slowlyo/owl-admin 安装问题说明

## 问题描述

尝试安装 `slowlyo/owl-admin` 时遇到依赖冲突问题。

## 根本原因

### PHP 版本不兼容

**当前环境**:
- PHP 版本: 8.3.8

**依赖冲突**:
- `tymon/jwt-auth` (已安装版本: 2.2.1)
- 依赖 `lcobucci/jwt` ^4.0
- `lcobucci/jwt` 4.0.4 依赖 `lcobucci/clock` ^2.0
- `lcobucci/clock` 2.3.0 要求 PHP ~8.1.0 || ~8.2.0
- **不支持 PHP 8.3**

## 解决方案

### 方案 1: 降级 PHP 版本 (推荐)

将 PHP 版本降级到 8.2:

```bash
# 使用 PHP 8.2
# 然后安装 slowlyo/owl-admin
composer require slowlyo/owl-admin
```

### 方案 2: 等待依赖更新

等待以下包更新以支持 PHP 8.3:
- `lcobucci/clock` 更新到支持 PHP 8.3
- `tymon/jwt-auth` 更新依赖

### 方案 3: 使用替代包

考虑使用其他支持 PHP 8.3 的后台管理包:
- `laravel-admin`
- `filament/filament`

## 当前项目状态

### ✅ 已完成的功能

1. **CORS 跨域支持** - 已配置
2. **API 频次限制** - 已实现
   - 默认每分钟 60 次限制
   - 每个API端点独立限制
   - 每个用户独立限制
   - 支持自定义限制
3. **压力测试** - 已通过
   - 单元测试: 5/5 通过
   - 压力测试: 70请求验证通过

### 📦 已安装的包

- Laravel Framework: ^12.0
- tymon/jwt-auth: ^2.2
- knuckleswtf/scribe: * (API文档生成)
- laravel/breeze: ^2.3

## 建议操作

### 立即可行的方案

**推荐使用 PHP 8.2 环境**:

1. 切换到 PHP 8.2
2. 清除依赖缓存:
   ```bash
   rm -rf vendor composer.lock
   composer install
   ```
3. 安装 owl-admin:
   ```bash
   composer require slowlyo/owl-admin
   ```

### 长期方案

1. 关注 `lcobucci/clock` 的更新
2. 关注 `tymon/jwt-auth` 的更新
3. 当这些包支持 PHP 8.3 后，可以升级

## 环境要求对比

| 包名 | 当前要求 | PHP 8.3 支持 |
|------|---------|-------------|
| laravel/framework | ^12.0 | ✅ 支持 |
| tymon/jwt-auth | ^2.2 | ❌ 依赖冲突 |
| lcobucci/clock | ^2.0 | ❌ 不支持 |
| slowlyo/owl-admin | - | ❌ 依赖冲突 |

## 总结

由于 PHP 8.3 与 `lcobucci/clock` 的兼容性问题，暂时无法安装 `slowlyo/owl-admin`。

**建议**:
- 使用 PHP 8.2 环境进行开发
- 或等待依赖包更新支持 PHP 8.3
- 或使用其他支持 PHP 8.3 的后台管理解决方案

当前项目的其他功能（CORS、频次限制、API文档）都已正常工作。
