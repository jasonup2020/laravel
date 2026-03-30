# Laravel SaaS Multi-Tenant Platform

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

企业级 SaaS 多租户权限脚手架，基于 Laravel 12 + PHP 8.3，一条命令自动生成完整项目结构，开箱即用。

## ✨ 核心特性

### 🏢 多租户架构
- **数据隔离**: 支持数据库隔离、字段隔离、域名隔离
- **租户管理**: 完整的租户CRUD操作
- **自动识别**: 基于域名/编码自动识别租户
- **配置管理**: 每个租户独立配置

### 🔐 RBAC 权限体系
- **用户管理**: 用户增删改查、状态管理
- **角色管理**: 角色定义、权限分配
- **权限管理**: 菜单权限、按钮权限、API权限
- **部门管理**: 部门树形结构、用户部门关联
- **岗位管理**: 岗位定义、用户岗位关联
- **职级管理**: 职级体系、用户职级关联

### 🔑 认证与安全
- **JWT双令牌**: Access Token + Refresh Token
- **Token黑名单**: 退出登录自动失效
- **单设备登录**: 新登录踢出旧设备
- **设备管理**: 设备列表、下线、日志
- **密码加密**: 随机盐值加密 `Hash::make($password . $salt)`
- **XSS防护**: 自动过滤危险标签
- **SQL注入防护**: 参数绑定、Eloquent ORM

### 🌐 API 开发规范
- **统一响应**: code + message + data + success
- **异常处理**: 全局异常捕获、统一错误响应
- **Filter Builder**: 动态查询构建器
- **分页协议**: 统一分页格式
- **RESTful**: 标准REST接口设计
- **表单验证**: FormRequest验证类
- **资源转换**: Resource数据格式化

### 📚 文档与工具
- **OpenAPI 3.0**: Swagger UI自动生成
- **Postman**: 集合自动导出
- **MD文档**: 完整接口文档

### ⚡ 底层服务
- **Redis**: 缓存、队列、限流
- **Horizon**: 队列监控面板
- **Queue**: 异步任务示例
- **Event**: 事件系统示例
- **Scheduler**: 定时任务
- **Log**: 标准化日志

### 🛠️ 开发效率
- **安装命令**: `php artisan saas:install`
- **CRUD生成**: `php artisan make:saas-crud ModuleName`
- **自动生成**: Migration/Model/Request/Service/Controller/Resource/Route/Test

### 🌍 多语言支持
- **中文**: 完整中文翻译
- **英文**: 完整英文翻译
- **韩文**: 完整韩文翻译

## 📋 环境要求

- PHP >= 8.3
- Composer >= 2.0
- MySQL >= 8.0
- Redis >= 7.0
- Nginx >= 1.20

## 🚀 快速开始

### 方式一：本地安装

```bash
# 1. 克隆项目
git clone https://github.com/your-repo/laravel-saas-platform.git
cd laravel-saas-platform

# 2. 安装依赖
composer install

# 3. 配置环境
cp .env.example .env
php artisan key:generate

# 4. 运行安装命令（自动执行迁移、数据填充等）
php artisan saas:install

# 5. 启动服务
php artisan serve
```

### 方式二：Docker部署

```bash
# 1. 克隆项目
git clone https://github.com/your-repo/laravel-saas-platform.git
cd laravel-saas-platform

# 2. 配置环境
cp .env.example .env

# 3. 启动Docker容器
docker-compose up -d

# 4. 进入容器安装依赖
docker-compose exec php bash
composer install
php artisan key:generate
php artisan saas:install

# 5. 访问应用
# http://localhost
```

## 📁 项目结构

```
├── app/
│   ├── Console/Commands/      # 命令类
│   │   ├── SaasInstall.php    # 安装命令
│   │   └── MakeSaasCrud.php   # CRUD生成命令
│   ├── Events/                # 事件类
│   ├── Exceptions/            # 异常类
│   │   ├── BusinessException.php
│   │   └── Handler.php
│   ├── Helpers/               # 辅助函数
│   │   ├── common.php
│   │   └── function.php
│   ├── Http/
│   │   ├── Controllers/       # 控制器
│   │   │   ├── BaseController.php
│   │   │   └── Api/V1/
│   │   ├── Middleware/        # 中间件
│   │   ├── Requests/          # 表单验证
│   │   └── Resources/         # 资源类
│   ├── Jobs/                  # 队列任务
│   ├── Listeners/             # 监听器
│   ├── Models/                # 模型
│   │   ├── BaseModel.php
│   │   ├── User.php
│   │   ├── Tenant.php
│   │   └── ...
│   ├── Services/              # 服务类
│   │   ├── BaseService.php
│   │   ├── UserService.php
│   │   └── ...
│   └── Traits/                # Trait
│       └── FilterBuilder.php
├── config/
│   └── saas.php              # SaaS配置
├── database/
│   ├── migrations/           # 数据库迁移
│   └── seeders/              # 数据填充
├── docker/                   # Docker配置
│   ├── nginx/
│   ├── php/
│   └── redis/
├── docs/                     # 文档
│   ├── MD/                   # Markdown文档
│   ├── OpenAPI/              # OpenAPI规范
│   └── Postman/              # Postman集合
├── lang/                     # 多语言
│   ├── en/
│   ├── zh/
│   └── ko/
├── routes/
│   └── api.php               # API路由
├── tests/                    # 测试
│   └── Feature/
├── .env.example              # 环境配置示例
├── docker-compose.yml        # Docker编排
└── README.md                 # 说明文档
```

## 🔧 配置说明

### 环境变量

```env
# 应用配置
APP_NAME="SaaS Platform"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://laravel-saas-ultimate-pro-full.test.com

# 数据库配置
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_saas
DB_USERNAME=laravel_saas
DB_PASSWORD=aesRs7T4S2

# Redis配置
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# JWT配置
JWT_ACCESS_TOKEN_TTL=120
JWT_REFRESH_TOKEN_TTL=10080

# 多租户配置
SAAS_TENANT_ENABLED=true
SAAS_TENANT_ISOLATION_MODE=column
```

## 📖 使用指南

### 登录获取Token

```bash
curl -X POST http://laravel-saas-ultimate-pro-full.test.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

### 使用Token访问API

```bash
curl -X GET http://laravel-saas-ultimate-pro-full.test.com/api/v1/users \
  -H "Authorization: Bearer {your_token}"
```

### 生成CRUD模块

```bash
# 生成Product模块的所有文件
php artisan make:saas-crud Product

# 自动生成以下文件：
# - database/migrations/xxxx_create_products_table.php
# - app/Models/Product.php
# - app/Http/Requests/Product/CreateProductRequest.php
# - app/Http/Requests/Product/UpdateProductRequest.php
# - app/Services/ProductService.php
# - app/Http/Controllers/Api/V1/ProductController.php
# - app/Http/Resources/ProductResource.php
# - tests/Feature/ProductTest.php
```

## 🧪 测试

```bash
# 运行所有测试
php artisan test

# 运行指定测试
php artisan test --filter=UserTest

# 生成测试覆盖率
php artisan test --coverage
```

## 📊 队列监控

访问 `/horizon` 查看队列监控面板。

## 🔒 默认账号

- **用户名**: admin
- **密码**: admin123

## 📝 API文档

- **Swagger UI**: `/api/documentation`
- **Postman**: 导入 `docs/Postman/postman_collection.json`
- **Markdown**: 查看 `docs/MD/API接口文档.md`

## 🤝 贡献指南

1. Fork 项目
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 提交 Pull Request

## 📄 许可证

本项目基于 MIT 许可证开源。详见 [LICENSE](LICENSE) 文件。

## 🙏 致谢

- [Laravel](https://laravel.com) - 优秀的PHP框架
- [JWT](https://jwt.io) - JSON Web Token
- [Docker](https://docker.com) - 容器化部署
- 所有贡献者

## 📞 联系方式

- **Email**: support@saas-platform.com
- **Website**: https://saas-platform.com
- **Documentation**: https://docs.saas-platform.com

---

**Made with ❤️ by SaaS Platform Team**
