<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use App\Services\TenantProvisioner;
use App\Support\Modules;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/** Session login for the browser console. The API (bearer tokens) is used by the Android app. */
class WebAuthController extends Controller
{
    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string', 'code' => 'nullable|string|size:6']);
        $user = User::where('email', $d['email'])->first();
        if (! $user || ! $user->is_active || ! Hash::check($d['password'], $user->password)) {
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
        Auth::guard('web')->login($user);
        $r->session()->regenerate();
        Audit::log('auth.web_login', 'user', $user->id, null, null, $user->tenant_id, $user->id);

        return response()->json(['redirect' => $user->is_platform_admin ? '/admin' : '/app']);
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

    public function logout(Request $r)
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->json(['redirect' => '/login']);
    }
}
