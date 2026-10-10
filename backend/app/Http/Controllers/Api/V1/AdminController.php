<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiError;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TenantModule;
use App\Models\User;
use App\Services\Audit;
use App\Support\Modules;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Users, roles, settings and module switches: the knobs a tenant owner turns. */
class AdminController extends Controller
{
    // ---- modules ----
    public function modules()
    {
        $tenant = app(Tenancy::class)->get();
        $enabled = $tenant->enabledModules();
        $allowed = $tenant->plan?->modules ?? Modules::switchable();

        return response()->json(['data' => collect(Modules::switchable())->map(fn ($k) => [
            'key' => $k, 'enabled' => in_array($k, $enabled, true), 'in_plan' => in_array($k, $allowed, true),
            'permissions' => Modules::forModule($k),
        ])->values()]);
    }

    public function setModule(Request $r, string $key)
    {
        abort_unless(in_array($key, Modules::switchable(), true), 404);
        $d = $r->validate(['enabled' => 'required|boolean', 'config' => 'nullable|array']);
        $tenant = app(Tenancy::class)->get();
        if ($d['enabled'] && $tenant->plan && ! in_array($key, $tenant->plan->modules ?? [], true)) {
            throw new ApiError('plan_limit', 'Your plan does not include this module. Upgrade to enable it.', 402);
        }
        $m = TenantModule::firstOrNew(['module_key' => $key]);
        $m->tenant_id = $tenant->id;
        $m->enabled = $d['enabled'];
        if (array_key_exists('config', $d)) {
            $m->config = $d['config'];
        }
        $m->save();
        Audit::log('module.'.($d['enabled'] ? 'enabled' : 'disabled'), 'module', $key);

        return response()->json(['data' => $m]);
    }

    // ---- settings (tenant-wide or per property; free-form keys so tenants can tailor behaviour) ----
    public function settings(Request $r)
    {
        $pid = $r->query('property_id');
        $rows = Setting::where('property_id', $pid)->get()->pluck('value', 'key');

        return response()->json(['data' => $rows]);
    }

    public function putSettings(Request $r)
    {
        $d = $r->validate(['values' => 'required|array|max:100', 'property_id' => ['nullable', 'uuid', Rule::exists('properties', 'id')->where('tenant_id', app(Tenancy::class)->id())]]);
        foreach ($d['values'] as $key => $value) {
            if (! preg_match('/^[a-z0-9_.\-]{1,80}$/i', (string) $key)) {
                throw new ApiError('validation_failed', "Invalid setting key: $key", 422);
            }
            $s = Setting::firstOrNew(['property_id' => $d['property_id'] ?? null, 'key' => $key]);
            $before = $s->value;
            $s->tenant_id = app(Tenancy::class)->id();
            $s->value = $value;
            $s->save();
            Audit::log('setting.changed', 'setting', $key, ['value' => $before], ['value' => $value]);
        }

        return $this->settings($r);
    }

    // ---- roles ----
    public function roles()
    {
        return response()->json(['data' => Role::orderBy('name')->get(), 'catalog' => Modules::all()]);
    }

    public function storeRole(Request $r)
    {
        $d = $r->validate(['key' => 'required|alpha_dash|max:40', 'name' => 'required|string|max:80', 'description' => 'nullable|string|max:200', 'permissions' => 'required|array',
            'permissions.*' => ['string', Rule::in(array_merge(['*'], Modules::all()))]]);
        if (Role::where('key', $d['key'])->exists()) {
            throw new ApiError('duplicate', 'Role key already exists.', 409);
        }
        $role = Role::create($d + ['is_system' => false]);
        Audit::log('role.created', 'role', $role->id, null, $role->toArray());

        return response()->json(['data' => $role], 201);
    }

    public function updateRole(Request $r, string $id)
    {
        $role = Role::findOrFail($id);
        if ($role->key === 'owner') {
            throw new ApiError('forbidden', 'The owner role cannot be changed.', 403);
        }
        $d = $r->validate(['name' => 'sometimes|string|max:80', 'description' => 'nullable|string|max:200', 'permissions' => 'sometimes|array',
            'permissions.*' => ['string', Rule::in(array_merge(['*'], Modules::all()))]]);
        $before = $role->toArray();
        $role->update($d);
        Audit::log('role.updated', 'role', $role->id, $before, $role->toArray());

        return response()->json(['data' => $role]);
    }

    public function destroyRole(string $id)
    {
        $role = Role::findOrFail($id);
        if ($role->is_system) {
            throw new ApiError('forbidden', 'System roles cannot be deleted. Edit it or create a new role.', 403);
        }
        $role->delete();
        Audit::log('role.deleted', 'role', $id);

        return response()->noContent();
    }

    // ---- users ----
    public function users()
    {
        return response()->json(['data' => User::with('roles:id,key,name')->orderBy('name')->get()]);
    }

    public function storeUser(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'phone' => 'nullable|string|max:30', 'password' => 'required|string|min:10',
            'pin' => 'nullable|digits_between:4,6', 'locale' => 'nullable|in:en,fr,rw', 'roles' => 'required|array|min:1', 'roles.*' => ['uuid', Rule::exists('roles', 'id')->where('tenant_id', app(Tenancy::class)->id())]]);
        $user = User::create(collect($d)->only(['name', 'email', 'phone', 'password', 'locale'])->all() + ['pin_hash' => isset($d['pin']) ? Hash::make($d['pin']) : null]);
        $this->syncRoles($user, $d['roles']);
        Audit::log('user.created', 'user', $user->id, null, ['email' => $user->email, 'roles' => $d['roles']]);

        return response()->json(['data' => $user->load('roles:id,key,name')], 201);
    }

    public function updateUser(Request $r, string $id)
    {
        $user = User::findOrFail($id);
        $d = $r->validate(['name' => 'sometimes|string|max:120', 'phone' => 'nullable|string|max:30', 'password' => 'sometimes|string|min:10', 'pin' => 'nullable|digits_between:4,6',
            'is_active' => 'sometimes|boolean', 'locale' => 'nullable|in:en,fr,rw', 'roles' => 'sometimes|array|min:1', 'roles.*' => ['uuid', Rule::exists('roles', 'id')->where('tenant_id', app(Tenancy::class)->id())]]);
        if (array_key_exists('is_active', $d) && ! $d['is_active'] && $user->id === $r->user()->id) {
            throw new ApiError('forbidden', 'You cannot deactivate yourself.', 403);
        }
        if (isset($d['pin'])) {
            $user->pin_hash = Hash::make($d['pin']);
        }
        $user->fill(collect($d)->only(['name', 'phone', 'password', 'is_active', 'locale'])->all())->save();
        if (isset($d['roles'])) {
            $this->syncRoles($user, $d['roles']);
        }
        if (isset($d['is_active']) && ! $d['is_active']) {
            $user->tokens()->delete();
        }
        Audit::log('user.updated', 'user', $user->id, null, collect($d)->except(['password', 'pin'])->all());

        return response()->json(['data' => $user->fresh()->load('roles:id,key,name')]);
    }

    public function auditLog(Request $r)
    {
        $q = AuditLog::where('tenant_id', app(Tenancy::class)->id())->orderByDesc('created_at');
        foreach (['entity', 'entity_id', 'user_id', 'action'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->query($f));
            }
        }

        return response()->json($q->paginate(min((int) $r->query('per_page', 50), 200)));
    }

    private function syncRoles(User $user, array $roleIds): void
    {
        $owner = Role::where('key', 'owner')->first();
        if ($owner && in_array($owner->id, $roleIds, true) && ! request()->user()->can2('*')) {
            throw new ApiError('forbidden', 'Only an owner can grant the owner role.', 403);
        }
        $user->roles()->detach();
        foreach ($roleIds as $rid) {
            $user->roles()->attach($rid, ['id' => (string) Str::uuid7()]);
        }
    }
}
