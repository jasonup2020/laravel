# AI 代码规范

## 开发规范
请严格按照以下 Laravel 12 官方规范 + 企业级开发标准生成完整代码，**必须包含：业务代码 + 单元测试 + MD接口文档**，禁止随意修改结构、命名和格式：
本地测试域名：https://laravel-saas-ultimate-pro-full.test.com/
APP_URL=http://laravel-saas-ultimate-pro-full.test.com


DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_saas
DB_USERNAME=laravel_saas
DB_PASSWORD=aesRs7T4S2

把 [docs\Helpers\common.php,docs\Helpers\function.php] 复制到  [app\Helpers\common.php,app\Helpers\function.php] 里面 在 [app\Providers\AppServiceProvider.php] 中注册

==================== 核心规范要求 ====================
1. 基础环境
- Laravel 版本：12 最新稳定版
- PHP 版本：≥8.2 | 8.3 (D:\BtSoft\php\82|D:\BtSoft\php\83)
- 编码标准：严格遵循 PSR-12
- 代码格式：4 空格缩进，UTF-8 编码，每行代码不超过 120 字符
- 代码必须加注释：类注释、方法注释、关键逻辑
- 禁止硬编码，配置写 .env + config/
- 数据库表名：复数小写下划线（users, user_roles）
- 字段名：小写下划线（user_id, created_at）
- 路由：小写短横线（user-list）
- 中间件/请求验证类：语义化命名（StoreUserRequest）
- 服务层：必须抽离业务逻辑，禁止在控制器写复杂业务逻辑
- 异常：必须定义：$fillable、$casts、关联关系
- 错误处理：try-catch + 自定义异常
- 模型必须定义：$fillable、$casts、关联关系
- 软删除：必须使用 $table->softDeletes()
- 数据库迁移文件：自定义异常类

2. 命名规范（建议）
- 类名：大驼峰（UserController）
- 方法名/变量名：小驼峰（getUserInfo）
- 配置/常量：全大写下划线（APP_NAME）
- 数据库表名：复数小写下划线（users, user_roles）
- 字段名：小写下划线（user_id, created_at）
- 路由：小写短横线（user-list）
- 中间件/请求验证类：语义化命名（StoreUserRequest）

3. 项目结构规范
- 控制器：app/Http/Controllers（资源控制器）
- 模型：app/Models（定义fillable、casts、关联、软删除）
- 请求验证：app/Http/Requests（独立验证类）
- 资源：app/Http/Resources（统一数据返回）
- 服务层：app/Services（业务逻辑全部抽离）
- 异常：app/Exceptions（自定义异常类）
- 迁移文件：database/migrations（按功能分类）
- 路由：routes/api.php（分组、命名、中间件）
- 测试文件：tests/Feature（对应功能的完整单元测试）

4. 代码编写规范
- 禁止在控制器写复杂业务逻辑，必须抽离到 Service
- 所有表单验证必须使用独立 FormRequest 类
- 数据库操作必须使用 Eloquent，禁止原生 SQL
- 统一使用 Laravel 响应格式：code+message+data
- 错误处理：try-catch + 自定义异常
- 模型必须定义：$fillable、$casts、关联关系
- 软删除：必须使用 $table->softDeletes()
- 时间字段：created_at, updated_at, deleted_at
- 代码必须加注释：类注释、方法注释、关键逻辑
- 禁止硬编码，配置写 .env + config/

5. API 接口规范（RESTful）
- 请求方式：GET查询、POST新增、PUT全量更新、PATCH部分更新、DELETE删除
- 统一返回格式： message(msg,true,data{},code,extra{}|null)  | success(msg{},code,data{}) | error(msg{},code,extra{})  - code: 200
  - message: "操作成功"
  - data: {}
- 失败返回格式： message(msg,false,data{},code,extra{}|null)  | error(msg{},code,data{}|null) | error(msg{},code,extra{}|null)
  - code: 400
  - message: "操作失败"
  - data: {}
- 状态码：200成功，400参数错误，401未授权，403无权限，422验证失败，500服务器错误

6. 安全规范
- 所有用户输入必须验证
- 使用 Laravel 自带密码哈希、CSRF、XSS 防护
- 敏感字段不返回（密码、密钥）
- 防 SQL 注入，使用参数绑定

==================== 必须生成的内容 ====================
1. 完整业务代码
- 迁移文件
- 模型文件
- 表单请求验证类
- 服务类
- 控制器
- 资源类
- 路由

2. 单元测试（必须完整）
- 路径：tests/Feature
- 覆盖：列表、详情、创建、更新、删除、验证错误、权限错误
- 使用模型工厂 Factory
- 使用 RefreshDatabase  trait
- 每条用例必须独立、可运行、断言正确

3. 完整 MD 接口文档
- 接口说明
- 基础地址
- 请求头
- 接口列表（含地址、方法、权限、描述）
- 请求参数
- 响应示例（成功+失败）
- 错误码说明
- 部署&调用说明

4. 统一要求
- 代码可直接复制运行，无语法错误
- 格式整洁、注释清晰、符合企业交付标准


### 文件组织结构

| 文件类型 | 路径 | 说明 | 要求 |
|---------|------|------|------|
| 迁移 | `database/migrations/` | 数据库迁移文件 | 按功能分类 |
| 模型 | `app/Models/` | Eloquent 模型 | 定义 fillable、casts、关联、软删除 |
| 请求验证 | `app/Http/Requests/` | 表单验证请求 | 独立验证类 |
| 服务 | `app/Services/` | 业务逻辑服务 | 业务逻辑全部抽离 |
| 控制器 | `app/Http/Controllers/` | HTTP 控制器 | 资源控制器 |
| 资源 | `app/Http/Resources/` | API 资源转换 | 统一数据返回 |
| 路由 | `routes/api.php` | API 路由定义 | 分组、命名、中间件 |
| 单元测试 | `tests/Feature/` | 功能测试 | 完整单元测试 |
| 接口文档 | `docs/MD/xxx 接口文档.md` | API 接口文档 | 完整 MD 文档 |

#### 要求

- **文件管理**：添加/删除文件时更新文档，保持结构清晰
- **数据库管理**：修改表结构时更新迁移文档，确保按顺序执行
- **代码规范**：遵循 Laravel 官方规范，使用 PHPDoc 注释，符合 PSR-12
- **测试要求**：每个 API 接口都需要完整单元测试
- **文档要求**：每个 API 接口都需要 MD 文档（接口描述、请求参数、响应示例、错误码）


--- - 项目结构规范 - 项目结构

{% include 'project-structure.md' %}

--- - 数据库迁移规范 - 数据库迁移文件

{% include 'database-migrations.md' %}

---

{% include 'helper-functions.md' %}

---

## 需要改进的地方

| 项目 | 主要问题 | 改进措施 |
|------|---------|---------|
| **API 文档** | 参数说明不完整、缺少版本管理、限流说明和调用示例 | 统一文档结构，补充完整参数、错误码、版本、限流说明及多语言示例 |
| **测试覆盖** | 边缘场景缺失、性能测试不足、覆盖率低于 80% | 补充边界测试、性能测试，提高覆盖率至 80%+ |
| **代码规范** | 注释不完整、格式不统一 | 补充 PHPDoc，使用 PHP_CodeSniffer 统一格式 |
| **安全加固** | 敏感数据日志、API 限流、请求签名、防重放攻击缺失 | 脱敏日志、实现可配置限流、签名验证、防重放、IP 白名单、异常登录检测 |
| **性能优化** | N+1 查询、缺少缓存机制、分页未优化 | 使用 with() 优化查询，集成 Redis 缓存，优化分页 |
| **可扩展性** | 服务耦合高、缺少插件化架构 | 使用依赖注入，实现插件化架构，统一配置管理 |

## 相关文档

- [Laravel 官方文档](https://laravel.com/docs)
- [PSR-12 编码规范](https://www.php-fig.org/psr/psr-12/)
