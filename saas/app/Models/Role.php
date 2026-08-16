<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'name', 'description', 'is_platform_role'];

    protected function casts(): array
    {
        return ['is_platform_role' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_key',
            'permission_key'
        );
    }

    public function grants(string $permissionKey): bool
    {
        return $this->permissions()
            ->where('permissions.key', $permissionKey)
            ->exists();
    }
}
