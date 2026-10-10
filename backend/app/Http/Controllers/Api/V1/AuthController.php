<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiError;
use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\Device;
use App\Models\PaymentMethod;
use App\Models\Property;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\Audit;
use App\Services\TenantProvisioner;
use App\Support\Modules;
use App\Support\Tenancy;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const ACCESS_MINUTES = 15;

    private const REFRESH_DAYS = 30;

    private function deviceRules(string $prefix = 'device.'): array
    {
        return [
            "{$prefix}id" => 'required|uuid',
            "{$prefix}name" => 'required|string|max:120',
            "{$prefix}platform" => 'required|in:android,web,ios',
            "{$prefix}app_version" => 'nullable|string|max:30',
            "{$prefix}push_token" => 'nullable|string',
        ];
    }

    public function register(Request $r, TenantProvisioner $p)
    {
        $d = $r->validate([
            'company' => 'required|string|max:150',
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:10',
            'locale' => 'nullable|in:en,fr,rw',
            'modules' => 'nullable|array',
            'modules.*' => 'in:'.implode(',', Modules::switchable()),
            'property_name' => 'nullable|string|max:150',
            'property_type' => 'nullable|string|max:40',
        ] + $this->deviceRules());
        [$tenant, $user] = $p->register($d);

        return response()->json($this->issue($user, $d['device']) + ['tenant' => ['id' => $tenant->id, 'slug' => $tenant->slug]], 201);
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string'] + $this->deviceRules());
        $user = User::where('email', $d['email'])->first();
        if (! $user || ! $user->is_active || ! Hash::check($d['password'], $user->password)) {
            Audit::log('auth.login_failed', 'user', null, null, ['email' => $d['email']]);
            throw new ApiError('invalid_credentials', 'Invalid email or password.', 422);
        }
        if ($user->hasTwoFactor()) {
            $c = TwoFactorChallenge::create(['user_id' => $user->id, 'device' => $d['device'], 'expires_at' => now()->addMinutes(5)]);

            return response()->json(['challenge_id' => $c->id], 202);
        }

        return response()->json($this->issue($user, $d['device']));
    }

    public function twoFactor(Request $r)
    {
        $d = $r->validate(['challenge_id' => 'required|uuid', 'code' => 'required|string|size:6']);
        $c = TwoFactorChallenge::find($d['challenge_id']);
        if (! $c || $c->expires_at->isPast() || $c->attempts >= 5) {
            throw new ApiError('challenge_expired', 'Challenge expired.', 422);
        }
        $user = User::find($c->user_id);
        if (! Totp::verify(decrypt($user->totp_secret), $d['code'])) {
            $c->increment('attempts');
            throw new ApiError('invalid_code', 'Invalid code.', 422);
        }
        $device = $c->device;
        $c->delete();

        return response()->json($this->issue($user, $device));
    }

    public function refresh(Request $r)
    {
        $d = $r->validate(['refresh_token' => 'required|string', 'device_id' => 'required|uuid']);
        $row = DB::table('refresh_tokens')->where('token_hash', hash('sha256', $d['refresh_token']))->first();
        if (! $row || $row->device_id !== $d['device_id']) {
            throw new ApiError('invalid_refresh', 'Invalid refresh token.', 401);
        }
        if ($row->used_at) { // reuse => assume theft: kill the device
            $this->revokeDevice($row->device_id, $row->user_id);
            throw new ApiError('device_revoked', 'Refresh token reuse detected; device revoked.', 401);
        }
        if (now()->gt($row->expires_at)) {
            throw new ApiError('invalid_refresh', 'Refresh token expired.', 401);
        }
        DB::table('refresh_tokens')->where('id', $row->id)->update(['used_at' => now()]);
        $user = User::find($row->user_id);
        $device = Device::withoutGlobalScopes()->find($row->device_id);
        if (! $user || ! $user->is_active || ! $device || $device->revoked_at) {
            throw new ApiError('device_revoked', 'This device has been revoked.', 401);
        }

        return response()->json($this->tokens($user, $device));
    }

    public function logout(Request $r)
    {
        $token = $r->user()->currentAccessToken();
        if ($token->device_id) {
            DB::table('refresh_tokens')->where('device_id', $token->device_id)->delete();
        }
        $token->delete();

        return response()->noContent();
    }

    public function twoFactorSetup(Request $r)
    {
        $user = $r->user();
        $secret = Totp::generateSecret();
        $user->forceFill(['totp_secret' => encrypt($secret), 'totp_confirmed_at' => null])->save();

        return response()->json(['secret' => $secret, 'uri' => Totp::uri($secret, $user->email, 'Edusmart Hospitality')]);
    }

    public function twoFactorConfirm(Request $r)
    {
        $d = $r->validate(['code' => 'required|string|size:6']);
        $user = $r->user();
        if (! $user->totp_secret || ! Totp::verify(decrypt($user->totp_secret), $d['code'])) {
            throw new ApiError('invalid_code', 'Invalid code.', 422);
        }
        $user->forceFill(['totp_confirmed_at' => now()])->save();
        Audit::log('auth.2fa_enabled', 'user', $user->id);

        return response()->noContent();
    }

    public function devices(Request $r)
    {
        return response()->json(['data' => Device::orderByDesc('last_seen_at')->get()]);
    }

    public function revoke(string $id)
    {
        $device = Device::findOrFail($id);
        $this->revokeDevice($device->id, $device->user_id);
        Audit::log('device.revoked', 'device', $device->id);

        return response()->noContent();
    }

    private function revokeDevice(string $deviceId, string $userId): void
    {
        Device::withoutGlobalScopes()->where('id', $deviceId)->update(['revoked_at' => now()]);
        DB::table('personal_access_tokens')->where('device_id', $deviceId)->delete();
        DB::table('refresh_tokens')->where('device_id', $deviceId)->delete();
    }

    public function context(Request $r)
    {
        $user = $r->user()->load('roles');
        $tenant = app(Tenancy::class)->get();
        $settings = Setting::whereNull('property_id')->get()->pluck('value', 'key');

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'phone', 'locale']) + ['roles' => $user->roles->pluck('key')],
            'tenant' => $tenant->only(['id', 'name', 'slug', 'status', 'currency', 'default_locale', 'timezone', 'trial_ends_at']),
            'properties' => Property::where('is_active', true)->get(['id', 'name', 'type', 'code']),
            'modules' => $tenant->enabledModules(),
            'permissions' => $user->permissions(),
            'locale' => $user->locale ?? $tenant->default_locale,
            'settings' => $settings,
            'tax_rates' => TaxRate::where('is_active', true)->get(['id', 'key', 'name', 'rate_bp', 'kind', 'inclusive']),
            'payment_methods' => PaymentMethod::where('is_active', true)->orderBy('position')->get(['id', 'key', 'name', 'type']),
            'custom_fields' => CustomField::where('is_active', true)->orderBy('position')->get(),
        ]);
    }

    /** Create/refresh the device row and mint an access + refresh token pair. */
    private function issue(User $user, array $dev): array
    {
        $device = app(Tenancy::class)->run($user->tenant ?? new Tenant, function () use ($user, $dev) {
            $device = Device::withoutGlobalScopes()->find($dev['id']);
            if ($device && ($device->tenant_id !== $user->tenant_id || $device->revoked_at)) {
                throw new ApiError('device_revoked', 'This device cannot be used.', 403);
            }
            $attrs = ['user_id' => $user->id, 'name' => $dev['name'], 'platform' => $dev['platform'],
                'app_version' => $dev['app_version'] ?? null, 'push_token' => $dev['push_token'] ?? null, 'last_seen_at' => now()];
            if ($device) {
                $device->forceFill($attrs)->save();
            } else {
                $device = new Device($attrs);
                $device->id = $dev['id'];
                $device->tenant_id = $user->tenant_id;
                $device->save();
            }

            return $device;
        });
        Audit::log('auth.login', 'user', $user->id, null, ['device' => $device->id], $user->tenant_id, $user->id);

        return $this->tokens($user, $device);
    }

    private function tokens(User $user, Device $device): array
    {
        $new = $user->createToken($device->name, ['*'], now()->addMinutes(self::ACCESS_MINUTES));
        $new->accessToken->forceFill(['device_id' => $device->id])->save();
        $refresh = Str::random(64);
        DB::table('refresh_tokens')->insert([
            'id' => (string) Str::uuid7(), 'user_id' => $user->id, 'device_id' => $device->id,
            'token_hash' => hash('sha256', $refresh), 'expires_at' => now()->addDays(self::REFRESH_DAYS),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['access_token' => $new->plainTextToken, 'refresh_token' => $refresh, 'expires_in' => self::ACCESS_MINUTES * 60];
    }
}
