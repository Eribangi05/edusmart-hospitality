@verbatim
<template x-if="$store.app.route.page === 'settings.modules'">
  <div x-data="modulesPage()" class="max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold" x-text="$store.app.t('modules')"></h1>
    <p class="mb-4 text-sm text-slate-500">Switch on only what your business uses. Turned-off modules disappear from the web console and the Android app. Data is kept.</p>
    <div class="space-y-3"><template x-for="m in rows" :key="m.key"><div class="card flex items-center justify-between gap-4">
      <div><p class="font-semibold capitalize" x-text="m.key.replace('_',' ')"></p><p class="text-sm text-slate-500" x-text="desc(m.key)"></p><p class="text-xs text-amber-700" x-show="!m.in_plan">Not included in your plan</p></div>
      <button class="btn" :class="m.enabled ? 'btn-primary' : 'btn-secondary'" :disabled="busy === m.key" @click="toggle(m)" x-text="m.enabled ? 'On' : 'Off'"></button></div></template></div>
  </div>
</template>

<template x-if="$store.app.route.page === 'settings.users'">
  <div x-data="usersPage()">
    <div class="mb-4 flex items-center gap-3"><h1 class="text-2xl font-bold" x-text="$store.app.t('users')"></h1><div class="flex-1"></div><button class="btn btn-primary" @click="newForm()">+ <span x-text="$store.app.t('add')"></span></button></div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th class="th" x-text="$store.app.t('name')"></th><th class="th">Email</th><th class="th" x-text="$store.app.t('roles')"></th><th class="th">Active</th><th class="th"></th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="u in rows" :key="u.id"><tr><td class="td font-medium" x-text="u.name"></td><td class="td" x-text="u.email"></td><td class="td" x-text="u.roles.map(r => r.name).join(', ')"></td><td class="td" x-text="u.is_active ? '✓' : ''"></td><td class="td text-right"><button class="btn btn-secondary btn-sm" @click="edit(u)" x-text="$store.app.t('edit')"></button></td></tr></template></tbody></table></div>
    <div x-show="form" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4"><form class="card my-8 w-full max-w-lg space-y-3" @submit.prevent="save()" x-show="form"><h2 class="text-lg font-semibold" x-text="form?.id ? 'Edit user' : 'New user'"></h2>
      <p x-show="err" x-text="err" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
      <template x-if="form"><div class="grid gap-3 sm:grid-cols-2">
        <label class="text-sm font-medium"><span x-text="$store.app.t('name')"></span><input class="input mt-1" x-model="form.name" required></label><label class="text-sm font-medium">Email<input type="email" class="input mt-1" x-model="form.email" :disabled="form.id" required></label>
        <label class="text-sm font-medium"><span x-text="$store.app.t('phone')"></span><input class="input mt-1" x-model="form.phone"></label>
        <label class="text-sm font-medium">Language<select class="input mt-1" x-model="form.locale"><option value="en">English</option><option value="fr">Français</option><option value="rw">Kinyarwanda</option></select></label>
        <label class="text-sm font-medium">Password<span x-show="form.id" class="font-normal text-slate-400"> (leave blank to keep)</span><input type="password" minlength="10" class="input mt-1" x-model="form.password" :required="!form.id"></label>
        <label class="text-sm font-medium">PIN (4-6 digits)<input inputmode="numeric" maxlength="6" class="input mt-1" x-model="form.pin"></label>
        <fieldset class="sm:col-span-2"><legend class="text-sm font-medium" x-text="$store.app.t('roles')"></legend><div class="mt-1 flex flex-wrap gap-2"><template x-for="r in roles" :key="r.id"><label class="flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-sm"><input type="checkbox" :checked="form.roles.includes(r.id)" @change="toggleRole(r.id)"> <span x-text="r.name"></span></label></template></div></fieldset>
        <label class="flex items-center gap-2 text-sm font-medium" x-show="form.id"><input type="checkbox" x-model="form.is_active"> Active</label>
      </div></template>
      <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="form = null" x-text="$store.app.t('cancel')"></button><button class="btn btn-primary" x-text="$store.app.t('save')"></button></div></form></div>
  </div>
</template>

<template x-if="$store.app.route.page === 'settings.roles'">
  <div x-data="rolesPage()" class="grid gap-4 lg:grid-cols-[16rem_1fr]">
    <aside class="space-y-2"><h1 class="text-2xl font-bold" x-text="$store.app.t('roles')"></h1>
      <template x-for="r in roles" :key="r.id"><button class="block w-full rounded-lg border px-3 py-2 text-left text-sm" :class="sel?.id === r.id ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white'" @click="pick(r)"><span class="font-medium" x-text="r.name"></span><span class="block text-xs text-slate-400" x-text="r.is_system ? 'built-in' : 'custom'"></span></button></template>
      <div class="flex gap-2"><input class="input" placeholder="New role name" x-model="newName"><button class="btn btn-secondary" @click="create()">+</button></div></aside>
    <section>
      <p x-show="!sel" class="text-slate-500">Pick a role to see and change what it can do. Every company can shape roles to match how their team works.</p>
      <template x-if="sel"><div class="card space-y-3"><div class="flex items-center justify-between"><h2 class="text-lg font-semibold" x-text="sel.name"></h2><span class="text-xs text-slate-400" x-show="sel.key === 'owner'">The owner role always has full access.</span></div>
        <p x-show="err" x-text="err" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"></p>
        <div class="overflow-x-auto"><table class="min-w-full text-sm"><tbody class="divide-y divide-slate-100"><template x-for="[mod, acts] in modules()" :key="mod"><tr><td class="w-36 py-2 font-medium capitalize" x-text="mod"></td><td class="py-2"><div class="flex flex-wrap gap-x-4 gap-y-1"><template x-for="a in acts" :key="a"><label class="flex items-center gap-1"><input type="checkbox" :checked="has(mod + '.' + a)" :disabled="sel.key === 'owner'" @change="flip(mod + '.' + a)"> <span x-text="a.replace('_',' ')"></span></label></template></div></td></tr></template></tbody></table></div>
        <div class="flex justify-between"><button class="btn btn-secondary text-red-600" x-show="!sel.is_system" @click="remove()" x-text="$store.app.t('delete')"></button><button class="btn btn-primary" x-show="sel.key !== 'owner'" @click="save()" x-text="$store.app.t('save')"></button></div></div></template>
    </section>
  </div>
</template>

<template x-if="$store.app.route.page === 'settings.labels'">
  <div x-data="labelsPage()" class="max-w-3xl space-y-6">
    <h1 class="text-2xl font-bold" x-text="$store.app.t('labels')"></h1>
    <section class="card space-y-3"><h2 class="font-semibold">Operating preferences</h2>
      <div class="grid gap-3 sm:grid-cols-2">
        <label class="text-sm font-medium">Check-in time<input type="time" class="input mt-1" x-model="prefs['rooms.checkin_time']"></label><label class="text-sm font-medium">Check-out time<input type="time" class="input mt-1" x-model="prefs['rooms.checkout_time']"></label>
        <label class="text-sm font-medium">Service charge on POS (%)<input type="number" step="0.1" min="0" class="input mt-1" x-model="prefs['pos.service_charge_bp']"></label><label class="text-sm font-medium">Tour deposit (%)<input type="number" min="0" max="100" class="input mt-1" x-model="prefs['tourism.deposit_percent']"></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="prefs['rooms.require_id_on_checkin']"> Require guest ID at check-in</label><label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="prefs['pos.require_manager_pin_for_void']"> Manager PIN to void after sending</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="prefs['pos.tips_enabled']"> Allow tips</label>
        <label class="text-sm font-medium sm:col-span-2">Receipt footer<input class="input mt-1" x-model="prefs['receipt.footer']"></label></div></section>
    <section class="card space-y-3"><h2 class="font-semibold">Rename things</h2><p class="text-sm text-slate-500">Call a "Guest" a "Visitor" or "Room" a "Cabin": override any label per language. Blank fields keep the default.</p>
      <template x-for="(v, k) in term" :key="k"><div class="grid items-center gap-2 sm:grid-cols-[8rem_1fr_1fr_1fr_auto]"><b class="text-sm" x-text="k + ' (' + dflt(k) + ')'"></b><template x-for="l in locales" :key="l"><input class="input" :placeholder="l" x-model="v[l]"></template><button class="text-xs text-red-600" @click="removeTerm(k)">remove</button></div></template>
      <div class="flex gap-2"><select class="input w-56" x-model="newKey"><template x-for="k in keys" :key="k"><option :value="k" x-text="k + ' – ' + dflt(k)"></option></template></select><button class="btn btn-secondary" @click="addTerm()">+ Add override</button></div></section>
    <button class="btn btn-primary" :disabled="busy" @click="save()" x-text="$store.app.t('save')"></button>
  </div>
</template>

<template x-if="$store.app.route.page === 'settings.devices'">
  <div x-data="devicesPage()">
    <h1 class="mb-1 text-2xl font-bold" x-text="$store.app.t('devices')"></h1><p class="mb-4 text-sm text-slate-500">Revoke a lost or stolen phone to sign it out immediately.</p>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th class="th" x-text="$store.app.t('name')"></th><th class="th">Platform</th><th class="th">App</th><th class="th">Last seen</th><th class="th"></th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="d in rows" :key="d.id"><tr><td class="td font-medium" x-text="d.name"></td><td class="td" x-text="d.platform"></td><td class="td" x-text="d.app_version"></td><td class="td" x-text="(d.last_seen_at || '').slice(0,16).replace('T',' ')"></td>
        <td class="td text-right"><span x-show="d.revoked_at" class="badge bg-red-100 text-red-700">revoked</span><button x-show="!d.revoked_at" class="btn btn-secondary btn-sm text-red-600" @click="revoke(d)">Revoke</button></td></tr></template>
      <tr x-show="!loading && !rows.length"><td class="td text-slate-400" colspan="5" x-text="$store.app.t('no_data')"></td></tr></tbody></table></div>
  </div>
</template>

<template x-if="$store.app.route.page === 'settings.audit'">
  <div x-data="auditPage()">
    <div class="mb-4 flex items-center gap-3"><h1 class="text-2xl font-bold" x-text="$store.app.t('audit')"></h1><input class="input w-60" placeholder="action e.g. order.voided" x-model.debounce.400ms="action" @input="load()"></div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th class="th">When</th><th class="th">Action</th><th class="th">Entity</th><th class="th">Details</th><th class="th">IP</th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="a in rows" :key="a.id"><tr><td class="td whitespace-nowrap" x-text="(a.created_at || '').slice(0,19).replace('T',' ')"></td><td class="td" x-text="a.action"></td><td class="td" x-text="(a.entity || '') + ' ' + (a.entity_id || '').slice(0,8)"></td><td class="td font-mono text-xs" x-text="short(a.after || a.before)"></td><td class="td" x-text="a.ip"></td></tr></template></tbody></table></div>
    <div class="mt-3 flex items-center gap-2 text-sm"><button class="btn btn-secondary btn-sm" :disabled="page <= 1" @click="load(page - 1)">‹</button><span x-text="page + ' / ' + last"></span><button class="btn btn-secondary btn-sm" :disabled="page >= last" @click="load(page + 1)">›</button></div>
  </div>
</template>

<template x-if="$store.app.route.page === 'settings.billing'">
  <div x-data="billingPage()" class="max-w-3xl space-y-5">
    <h1 class="text-2xl font-bold" x-text="$store.app.t('billing')"></h1>
    <template x-if="b"><div class="space-y-5">
      <div class="card flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm text-slate-500">Status</p><p class="text-xl font-bold capitalize" x-text="b.status"></p><p class="text-sm text-slate-500" x-text="b.plan ? b.plan.name : ''"></p></div>
        <div class="text-sm text-slate-600"><p x-show="b.status === 'trial'">Trial ends <b x-text="(b.trial_ends_at || '').slice(0,10)"></b></p><p x-show="b.paid_until">Paid until <b x-text="b.paid_until.slice(0,10)"></b></p></div></div>
      <div class="grid gap-3 sm:grid-cols-3"><template x-for="p in b.plans" :key="p.id"><div class="card space-y-2"><p class="font-semibold" x-text="p.name"></p><p class="text-2xl font-bold" x-text="money(p.price_monthly)"></p><p class="text-xs text-slate-500">per month</p><p class="text-xs capitalize text-slate-600" x-text="(p.modules || []).join(', ')"></p><button class="btn btn-primary w-full" @click="choose(p)">Choose</button></div></template></div>
      <div class="card space-y-3"><h2 class="font-semibold">Invoices</h2>
        <label class="text-sm font-medium">MTN MoMo number to charge<input class="input mt-1 max-w-xs" placeholder="2507…" x-model="phone"></label>
        <template x-for="i in b.invoices" :key="i.id"><div class="flex flex-wrap items-center justify-between gap-2 border-t pt-2 text-sm"><span><b x-text="i.number"></b> · <span x-text="money(i.amount)"></span></span><span class="flex items-center gap-2"><span class="badge capitalize" :class="i.status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'" x-text="i.status"></span><button x-show="i.status === 'pending'" class="btn btn-primary btn-sm" :disabled="busy" @click="pay(i)">Pay with MoMo</button></span></div></template>
        <p x-show="!b.invoices.length" class="text-sm text-slate-500">No invoices yet. Choose a plan above to create one.</p></div>
    </div></template>
  </div>
</template>
@endverbatim
