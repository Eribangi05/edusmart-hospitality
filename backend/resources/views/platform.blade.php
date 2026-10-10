<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Platform admin · Edusmart Hospitality</title>
  @vite(['resources/css/app.css', 'resources/js/platform.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
@verbatim
<div x-data="platform()" x-cloak class="mx-auto max-w-6xl p-4 lg:p-6">
  <header class="mb-6 flex items-center gap-3"><h1 class="text-2xl font-bold text-emerald-700">Platform admin</h1><div class="flex-1"></div>
    <nav class="flex gap-1"><template x-for="t in ['overview','tenants','plans','audit']"><button class="btn btn-secondary btn-sm capitalize" :class="tab === t ? 'ring-2 ring-emerald-500' : ''" @click="go(t)" x-text="t"></button></template></nav>
    <button class="btn btn-secondary btn-sm" @click="logout()">Log out</button></header>
  <p x-show="error" x-text="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
  <div x-show="needs2fa" class="card mb-4 space-y-2"><p class="font-semibold">Two-factor authentication is required for the platform console.</p>
    <button class="btn btn-primary" @click="setup2fa()">Set up authenticator</button>
    <template x-if="secret"><div class="space-y-2 text-sm"><p>Add this secret to your authenticator app: <code class="rounded bg-slate-100 px-1" x-text="secret"></code></p><p class="break-all text-xs text-slate-500" x-text="uri"></p><div class="flex gap-2"><input class="input w-32" placeholder="6-digit code" x-model="code"><button class="btn btn-primary" @click="confirm2fa()">Confirm</button></div></div></template></div>

  <section x-show="tab === 'overview' && metrics">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><template x-for="[k, v] in cards()" :key="k"><div class="card"><p class="text-sm text-slate-500" x-text="k"></p><p class="mt-1 text-2xl font-bold" x-text="v"></p></div></template></div>
  </section>

  <section x-show="tab === 'tenants'">
    <div class="mb-3 flex gap-2"><input class="input w-64" placeholder="Search company…" x-model.debounce.300ms="q" @input="loadTenants()"><select class="input w-auto" x-model="status" @change="loadTenants()"><option value="">All</option><template x-for="s in ['trial','active','grace','suspended']"><option :value="s" x-text="s"></option></template></select></div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th class="th">Company</th><th class="th">Plan</th><th class="th">Status</th><th class="th">Created</th><th class="th"></th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="t in tenants" :key="t.id"><tr><td class="td font-medium" x-text="t.name"></td><td class="td" x-text="t.plan?.name || '—'"></td><td class="td"><span class="badge capitalize" :class="{ 'bg-emerald-100 text-emerald-800': t.status === 'active', 'bg-sky-100 text-sky-800': t.status === 'trial', 'bg-amber-100 text-amber-800': t.status === 'grace', 'bg-red-100 text-red-700': t.status === 'suspended' }" x-text="t.status"></span></td><td class="td" x-text="t.created_at.slice(0,10)"></td><td class="td text-right"><button class="btn btn-secondary btn-sm" @click="openTenant(t.id)">Open</button></td></tr></template></tbody></table></div>
  </section>

  <div x-show="tenant" x-cloak class="fixed inset-0 z-40 flex justify-end bg-black/40" @click.self="tenant = null"><div class="h-full w-full max-w-lg space-y-4 overflow-y-auto bg-white p-5 shadow-xl" x-show="tenant"><template x-if="tenant"><div class="space-y-4">
    <div class="flex items-start justify-between"><div><h2 class="text-xl font-bold" x-text="tenant.name"></h2><p class="text-sm text-slate-500" x-text="tenant.slug + ' · ' + tenant.users + ' users'"></p></div><button class="btn btn-secondary btn-sm" @click="tenant = null">Close</button></div>
    <p class="text-sm" x-show="tenant.owner">Owner: <b x-text="tenant.owner?.name"></b> · <span x-text="tenant.owner?.email"></span> · <span x-text="tenant.owner?.phone"></span></p>
    <p class="text-sm">Modules: <span x-text="tenant.modules.join(', ') || '—'"></span></p>
    <div class="card space-y-2"><h3 class="font-semibold">Status</h3><div class="flex flex-wrap gap-2"><template x-for="s in ['trial','active','grace','suspended']"><button class="btn btn-secondary btn-sm capitalize" :class="tenant.status === s ? 'ring-2 ring-emerald-500' : ''" @click="setStatus(s)" x-text="s"></button></template></div></div>
    <div class="card space-y-2"><h3 class="font-semibold">Plan</h3><div class="flex gap-2"><select class="input" x-model="planId"><template x-for="p in plans" :key="p.id"><option :value="p.id" x-text="p.name"></option></template></select><button class="btn btn-secondary" @click="setPlan()">Apply</button></div></div>
    <div class="card space-y-2"><h3 class="font-semibold">Support access</h3><input class="input" placeholder="Reason (recorded in the audit log)" x-model="reason"><button class="btn btn-secondary" :disabled="!reason" @click="impersonate()">Open workspace as owner (30 min)</button></div>
    <div class="card space-y-1"><h3 class="font-semibold">Invoices</h3><template x-for="i in tenant.invoices" :key="i.id"><div class="flex items-center justify-between text-sm"><span x-text="i.number + ' · RWF ' + Number(i.amount).toLocaleString()"></span><span class="flex items-center gap-2"><span class="capitalize" x-text="i.status"></span><button x-show="i.status === 'pending'" class="btn btn-secondary btn-sm" @click="markPaid(i)">Mark paid</button></span></div></template></div>
  </div></template></div></div>

  <section x-show="tab === 'plans'" class="space-y-3">
    <template x-for="p in plans" :key="p.id"><div class="card grid items-center gap-3 sm:grid-cols-[1fr_8rem_auto]"><div><p class="font-semibold" x-text="p.name + ' (' + p.key + ')'"></p><p class="text-xs text-slate-500" x-text="(p.modules || []).join(', ')"></p></div><input class="input" type="number" x-model.number="p.price_monthly"><button class="btn btn-secondary" @click="savePlan(p)">Save</button></div></template>
  </section>

  <section x-show="tab === 'audit'"><div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th class="th">When</th><th class="th">Action</th><th class="th">Tenant</th><th class="th">Details</th></tr></thead>
    <tbody class="divide-y divide-slate-100"><template x-for="a in audit" :key="a.id"><tr><td class="td whitespace-nowrap" x-text="(a.created_at || '').slice(0,19).replace('T',' ')"></td><td class="td" x-text="a.action"></td><td class="td" x-text="(a.tenant_id || '').slice(0,8)"></td><td class="td font-mono text-xs" x-text="JSON.stringify(a.after || a.before || '').slice(0,100)"></td></tr></template></tbody></table></div></section>
</div>
@endverbatim
</body>
</html>
