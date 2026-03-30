<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 用户模型
 */
class User extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'password',
        'random_code',
        'avatar',
        'department_id',
        'position_id',
        'level_id',
        'locale',
        'timezone',
        'status',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'random_code',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'department_id' => 'integer',
        'position_id' => 'integer',
        'level_id' => 'integer',
        'status' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'email_verified_at' => 'datetime',
    ];

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    const GENDER_UNKNOWN = 0;
    const GENDER_MALE = 1;
    const GENDER_FEMALE = 2;

    /**
     * 设置密码（自动生成随机码并加密）
     */
    public function setPasswordAttribute($value)
    {
        if ($value) {
            // 如果random_code已经设置，使用现有的；否则生成新的
            if (!isset($this->attributes['random_code'])) {
                $this->attributes['random_code'] = Str::random(6);
            }
            $this->attributes['password'] = Hash::make($value . $this->attributes['random_code']);
        }
    }

    /**
     * 验证密码
     */
    public function verifyPassword(string $password): bool
    {
        return Hash::check($password . $this->random_code, $this->password);
    }

    /**
     * 获取用户的角色
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * 获取用户的部门
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    /**
     * 获取用户的岗位
     */
    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id', 'id');
    }

    /**
     * 获取用户的职级
     */
    public function level()
    {
        return $this->belongsTo(Level::class, 'level_id', 'id');
    }

    /**
     * 获取用户的设备
     */
    public function devices()
    {
        return $this->hasMany(Device::class, 'user_id', 'id');
    }

    /**
     * 获取用户的所有权限
     */
    public function getPermissions()
    {
        $permissions = collect([]);
        
        foreach ($this->roles as $role) {
            $permissions = $permissions->merge($role->permissions);
        }
        
        return $permissions->unique('id');
    }

    /**
     * 检查用户是否有某个权限
     */
    public function hasPermission(string $permission): bool
    {
        return $this->getPermissions()->contains('code', $permission);
    }

    /**
     * 检查用户是否有某个角色
     */
    public function hasRole(string $role): bool
    {
        return $this->roles->contains('code', $role);
    }

    /**
     * 作用域：启用的用户
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 作用域：根据租户查询
     */
    public function scopeByTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
