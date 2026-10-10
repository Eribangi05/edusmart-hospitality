<?php

namespace App\Models;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $guarded = ['id', 'tenant_id', 'is_platform_admin'];

    protected $hidden = ['password', 'pin_hash', 'totp_secret', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'totp_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * Inside a tenant context only that tenant's users are visible. With no
     * context (login, platform console) the lookup is unscoped on purpose.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $b) {
            if ($id = app(Tenancy::class)->id()) {
                $b->where('users.tenant_id', $id);
            }
        });
        static::creating(function (User $u) {
            if ($id = app(Tenancy::class)->id()) {
                $u->tenant_id = $id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')->withoutGlobalScopes()->withPivot('property_id');
    }

    public function hasTwoFactor(): bool
    {
        return $this->totp_confirmed_at !== null;
    }

    /** @return string[] effective permission strings */
    public function permissions(): array
    {
        return $this->roles->flatMap(fn ($r) => $r->permissions ?? [])->unique()->values()->all();
    }

    public function can2(string $permission): bool
    {
        $perms = $this->permissions();
        if (in_array('*', $perms, true) || in_array($permission, $perms, true)) {
            return true;
        }
        [$module] = explode('.', $permission, 2);

        return in_array("$module.*", $perms, true);
    }
}
