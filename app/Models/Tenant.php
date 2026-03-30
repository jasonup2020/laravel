<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 租户模型
 * 
 * 管理多租户系统中的租户信息
 * 支持租户配置、域名绑定、过期时间等功能
 */
class Tenant extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'tenants';

    /**
     * 可批量赋值的字段
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'domain',
        'logo',
        'contact_name',
        'contact_phone',
        'contact_email',
        'address',
        'config',
        'expire_at',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * 字段类型转换
     *
     * @var array
     */
    protected $casts = [
        'config' => 'json',
        'expire_at' => 'datetime',
        'status' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0;  // 禁用
    const STATUS_ENABLED = 1;   // 启用

    /**
     * 获取租户下的所有用户
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        return $this->hasMany(User::class, 'tenant_id', 'id');
    }

    /**
     * 获取租户下的所有角色
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function roles()
    {
        return $this->hasMany(Role::class, 'tenant_id', 'id');
    }

    /**
     * 获取租户下的所有部门
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function departments()
    {
        return $this->hasMany(Department::class, 'tenant_id', 'id');
    }

    /**
     * 检查租户是否已过期
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (empty($this->expire_at)) {
            return false;
        }
        return now()->gt($this->expire_at);
    }

    /**
     * 检查租户是否启用
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED && !$this->isExpired();
    }

    /**
     * 获取租户配置项
     *
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function getConfig(string $key = null, $default = null)
    {
        $config = $this->config ?? [];
        
        if ($key === null) {
            return $config;
        }
        
        return data_get($config, $key, $default);
    }

    /**
     * 设置租户配置项
     *
     * @param string|array $key 配置键名或配置数组
     * @param mixed $value 配置值
     * @return void
     */
    public function setConfig($key, $value = null): void
    {
        $config = $this->config ?? [];
        
        if (is_array($key)) {
            $config = array_merge($config, $key);
        } else {
            data_set($config, $key, $value);
        }
        
        $this->config = $config;
    }

    /**
     * 作用域：启用的租户
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', self::STATUS_ENABLED)
            ->where(function ($q) {
                $q->whereNull('expire_at')
                    ->orWhere('expire_at', '>', now());
            });
    }

    /**
     * 作用域：根据域名查询
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $domain
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    /**
     * 作用域：根据编码查询
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $code
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}
