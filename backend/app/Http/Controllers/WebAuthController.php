<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use App\Services\TenantProvisioner;
use App\Support\Modules;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

/** Session login for the browser console. The API (bearer tokens) is used by the Android app. */
class WebAuthController extends Controller
{
    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string', 'code' => 'nullable|string|size:6']);
        $key = 'weblogin:'.strtolower($d['email']).'|'.$r->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['error' => ['code' => 'too_many_attempts', 'message' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']], 429);
        }
        $user = User::where('email', $d['email'])->first();
        if (! $user || ! $user->is_active || ! Hash::check($d['password'], $user->password)) {
            RateLimiter::hit($key, 300);
            Audit::log('auth.login_failed', 'user', null, null, ['email' => $d['email'], 'channel' => 'web']);

            return response()->json(['error' => ['code' => 'invalid_credentials', 'message' => 'Invalid email or password.']], 422);
        }
        if ($user->hasTwoFactor()) {
            if (empty($d['code'])) {
                return response()->json(['two_factor' => true], 202);
            }
            if (! Totp::verify(decrypt($user->totp_secret), $d['code'])) {
                return response()->json(['error' => ['code' => 'invalid_code', 'message' => 'Invalid code.']], 422);
            }
        }
        RateLimiter::clear($key);
        Auth::guard('web')->login($user);
        $r->session()->regenerate();
        Audit::log('auth.web_login', 'user', $user->id, null, null, $user->tenant_id, $user->id);

        return response()->json(['redirect' => $this->landing($user)]);
    }

    public function register(Request $r, TenantProvisioner $p)
    {
        $d = $r->validate([
            'company' => 'required|string|max:150', 'name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:10', 'locale' => 'nullable|in:en,fr,rw', 'modules' => 'nullable|array', 'modules.*' => 'in:'.implode(',', Modules::switchable()),
            'property_name' => 'nullable|string|max:150', 'property_type' => 'nullable|string|max:40',
        ]);
        [, $owner] = $p->register($d);
        Auth::guard('web')->login($owner);
        $r->session()->regenerate();

        return response()->json(['redirect' => '/app'], 201);
    }

    public function forgot(Request $r)
    {
        $d = $r->validate(['email' => 'required|email']);
        Password::sendResetLink(['email' => $d['email']]);

        return response()->json(['message' => 'If that email has an account, a reset link is on its way.']);
    }

    public function reset(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'token' => 'required|string', 'password' => 'required|string|min:10']);
        $status = Password::reset($d, function (User $user, string $password) {
            $user->forceFill(['password' => $password])->save();
            DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();
            DB::table('refresh_tokens')->where('user_id', $user->id)->delete();
            Audit::log('auth.password_reset', 'user', $user->id, null, null, $user->tenant_id, $user->id);
        });
        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['error' => ['code' => 'invalid_reset_token', 'message' => 'This reset link is invalid or has expired.']], 422);
        }

        return response()->json(['redirect' => '/login']);
    }

    private function landing(User $user): string
    {
        if ($user->is_platform_admin) {
            return '/admin';
        }
        $ws = \App\Support\Workspaces::for($user, $user->tenant?->enabledModules() ?? []);

        return \App\Support\Workspaces::landing($ws, $user);
    }

    public function logout(Request $r)
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->json(['redirect' => '/login']);
    }
}
