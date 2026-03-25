# Laravel 12 多租户项目部署文档

## 目录
- [环境要求](#环境要求)
- [代码部署步骤](#代码部署步骤)
- [前端资源构建](#前端资源构建)
- [Web 服务器配置](#web-服务器配置)
- [队列配置](#队列配置)
- [定时任务配置](#定时任务配置)
- [安全建议](#安全建议)
- [监控与维护](#监控与维护)
- [项目特定注意事项](#项目特定注意事项)

---

## 环境要求

- PHP >= 8.2
- Composer
- Node.js & npm
- 数据库(MySQL/PostgreSQL/SQLite)
- Web 服务器(Nginx/Apache)

---

## 代码部署步骤

### 1. 进入项目目录
```bash
cd /path/to/your/project
```

### 2. 安装 Composer 依赖
```bash
composer install --optimize-autoloader --no-dev
```

### 3. 复制环境配置文件
```bash
cp .env.example .env
```

### 4. 生成应用密钥
```bash
php artisan key:generate
```

### 5. 编辑 .env 文件
配置以下环境变量:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_HOST=your-database-host
DB_DATABASE=your-database-name
DB_USERNAME=your-database-username
DB_PASSWORD=your-database-password
```

### 6. 运行数据库迁移
```bash
php artisan migrate --force
```

### 7. 优化缓存
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 8. 设置目录权限
```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## 前端资源构建

### 1. 安装 npm 依赖
```bash
npm install
```

### 2. 构建生产环境资源
```bash
npm run build
```

---

## Web 服务器配置

### Nginx 配置示例

创建 Nginx 配置文件 `/etc/nginx/sites-available/your-project`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/your/project/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

启用站点:
```bash
sudo ln -s /etc/nginx/sites-available/your-project /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 队列配置

### 1. 安装 Supervisor
```bash
sudo apt-get install supervisor
```

### 2. 创建队列配置文件
创建 `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
```

### 3. 启动 Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

---

## 定时任务配置

### 添加到 crontab
```bash
crontab -e
```

添加以下行(每分钟执行一次 Laravel 调度器):
```bash
* * * * * php /path/to/your/project/artisan schedule:run >> /dev/null 2>&1
```

---

## 安全建议

1. 确保 `APP_ENV` 设置为 `production`
2. 设置 `APP_DEBUG` 为 `false`
3. 配置 HTTPS 证书
4. 设置适当的文件权限
5. 定期备份数据库
6. 配置防火墙规则
7. 启用 CSRF 保护
8. 使用强密码
9. 定期更新依赖包

---

## 监控与维护

### 查看日志
```bash
tail -f storage/logs/laravel.log
```

### 清理缓存
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 优化生产环境
```bash
php artisan optimize
```

---

## 项目特定注意事项

由于项目包含 `tymon/jwt-auth` 包,需要额外执行以下步骤:

### 生成 JWT 密钥
```bash
php artisan jwt:secret
```

### 配置 JWT
在 `.env` 文件中设置:
```
JWT_SECRET=your-generated-secret-key
```

---

## 故障排查

### 常见问题

1. **权限错误**: 确保存储目录具有正确的写权限
2. **502 Bad Gateway**: 检查 PHP-FPM 是否正常运行
3. **数据库连接失败**: 验证 `.env` 中的数据库配置
4. **JWT 认证失败**: 确认 JWT_SECRET 已正确设置

### 调试模式

如需调试,临时在 `.env` 中设置:
```
APP_DEBUG=true
```
调试完成后记得改回 `false`。

---

## 更新部署

### 更新代码
```bash
git pull origin main
```

### 更新依赖
```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

### 运行迁移
```bash
php artisan migrate --force
```

### 清理并重建缓存
```bash
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 重启队列 Worker
```bash
sudo supervisorctl restart laravel-worker:*
```

---

## 备份策略

### 数据库备份
```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

### 文件备份
```bash
tar -czf storage_backup_$(date +%Y%m%d).tar.gz storage/
```

---

## 联系支持

如有问题,请联系技术支持团队。
