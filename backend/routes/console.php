<?php

use App\Models\Domain\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Payments\PaymentService;
use App\Services\Rooms\RoomService;
use App\Services\TenantEraser;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('rooms:night-audit {--date=}', function () {
    $date = $this->option('date') ? Carbon::parse($this->option('date')) : null;
    foreach (Tenant::whereIn('status', ['trial', 'active', 'grace'])->get() as $t) {
        if (! in_array('rooms', $t->enabledModules(), true)) {
            continue;
        }
        $n = app(Tenancy::class)->run($t, fn () => app(RoomService::class)->nightAudit($date));
        $this->info("{$t->slug}: $n charges posted");
    }
})->purpose('Post nightly room charges for all in-house guests');

Artisan::command('billing:run', function () {
    $this->info(json_encode(app(BillingService::class)->run()));
})->purpose('Invoice, move to grace and suspend tenants');

Artisan::command('payments:reconcile', function () {
    $n = 0;
    Payment::withoutGlobalScopes()->where('status', 'pending')->where('method_type', 'momo')->where('created_at', '<', now()->subMinute())->whereNotNull('reference')->limit(200)->get()
        ->each(function ($p) use (&$n) {
            app(Tenancy::class)->run(Tenant::findOrFail($p->tenant_id), fn () => app(PaymentService::class)->refresh($p));
            $n++;
        });
    $this->info("checked $n pending mobile-money payments");
})->purpose('Settle or fail pending mobile-money payments');

Artisan::command('housekeeping:prune', function () {
    DB::table('refresh_tokens')->where('expires_at', '<', now()->subDays(7))->delete();
    DB::table('two_factor_challenges')->where('expires_at', '<', now())->delete();
    DB::table('sync_receipts')->where('created_at', '<', now()->subDays(30))->delete();
})->purpose('Remove expired tokens, challenges and old sync receipts');

Artisan::command('platform:create-admin {email} {name} {password}', function (string $email, string $name, string $password) {
    $u = new User(['name' => $name, 'email' => $email, 'password' => $password]);
    $u->forceFill(['is_platform_admin' => true, 'email_verified_at' => now()])->save();
    $this->info('Platform admin created. Enable 2FA (POST /api/v1/auth/2fa/setup) before using the console.');
})->purpose('Create a platform super-admin');

Artisan::command('tenant:delete {slug} {--force}', function (string $slug) {
    $t = Tenant::withTrashed()->where('slug', $slug)->first();
    if (! $t) {
        return $this->error('No such tenant');
    }
    if (! $this->option('force') && ! $this->confirm("Permanently erase {$t->name} and ALL its data?")) {
        return;
    }
    $this->info(json_encode(app(TenantEraser::class)->erase($t)));
})->purpose('Permanently erase a company and all its data');

Schedule::command('rooms:night-audit')->dailyAt('02:00')->timezone('Africa/Kigali');
Schedule::command('billing:run')->dailyAt('03:00')->timezone('Africa/Kigali');
Schedule::command('payments:reconcile')->everyFiveMinutes();
Schedule::command('housekeeping:prune')->dailyAt('04:00')->timezone('Africa/Kigali');
