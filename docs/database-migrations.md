### 已创建的迁移文件

| 序号 | 表名 | 说明 | 主要字段 |
|------|------|------|---------|
| 1 | `users` | 用户表 | id, name, email, email_verified_at, password, random_code, status, remark, tenant_id, avatar, phone, last_login, locale, timezone, rememberToken(), timestamps() |
| 2 | `tenants` | 租户表 | id, name, domain, plan, expires_at, is_active, timestamps() |
| 3 | `roles` | 角色表 | id, name, guard_name, permissions, timestamps() |
| 4 | `permissions` | 权限表 | id, name, guard_name, module, action, description, timestamps() |
| 5 | `model_has_permissions` | 权限关联表 | permission_id, model_type, model_id, timestamps() |
| 6 | `model_has_roles` | 角色关联表 | role_id, model_type, model_id, timestamps() |
| 7 | `device_tokens` | 设备令牌表 | id, user_id, device_id, device_name, device_type, ip_address, user_agent, token, last_activity, is_active, timestamps() |
| 8 | `token_blacklists` | 黑名单表 | id, token, user_id, reason, expires_at, timestamps() |
| 9 | `api_logs` | API日志表 | id, user_id, method, path, ip_address, user_agent, request_data, response_data, response_time, status_code, expires_at, deleted_at, timestamps() |
| 10 | `jobs` | 作业表 | id, queue, payload, attempts, reserved_at, available_at, created_at |
| 11 | `job_batches` | 作业批次表 | id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at |
| 12 | `failed_jobs` | 失败作业表 | id, uuid, connection, queue, payload, exception, failed_at |
| 13 | `password_reset_tokens` | 密码重置表 | email, token, created_at |
| 14 | `sessions` | 会话表 | id, user_id, ip_address, user_agent, payload, last_activity |
| 15 | `cache` | 缓存表 | key, value, expiration |
| 16 | `cache_locks` | 缓存锁表 | key, owner, expiration |
