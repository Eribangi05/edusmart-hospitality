@verbatim
<template x-if="$store.app.route.page === 'tours.bookings'">
  <div x-data="bookingsPage()">
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <h1 class="text-2xl font-bold" x-text="$store.app.t('bookings')"></h1>
      <input class="input w-56" :placeholder="$store.app.t('search')" x-model.debounce.300ms="q" @input="load()">
      <select class="input w-auto" x-model="status" @change="load()"><option value="">All</option><template x-for="s in ['inquiry','confirmed','paid','in_progress','completed','cancelled','no_show']"><option :value="s" x-text="s.replace('_',' ')"></option></template></select>
      <div class="flex-1"></div>
      <button class="btn btn-primary" @click="newForm()" x-show="$store.app.can('tourism.create')">+ New booking</button>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-full divide-y divide-slate-200">
      <thead class="bg-slate-50"><tr><th class="th">#</th><th class="th" x-text="$store.app.t('date')"></th><th class="th" x-text="$store.app.t('name')"></th><th class="th">Pax</th><th class="th" x-text="$store.app.t('status')"></th><th class="th" x-text="$store.app.t('total')"></th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="b in rows" :key="b.id"><tr class="cursor-pointer hover:bg-slate-50" @click="open(b.id)">
        <td class="td font-medium" x-text="b.number"></td><td class="td" x-text="b.date.slice(0,10) + (b.start_time ? ' ' + b.start_time : '')"></td><td class="td" x-text="b.customer_name"></td><td class="td" x-text="b.pax_adults + b.pax_children"></td>
        <td class="td"><span class="badge capitalize" :class="badge(b.status)" x-text="b.status.replace('_',' ')"></span></td><td class="td" x-text="money(b.total)"></td></tr></template>
      <tr x-show="!loading && !rows.length"><td class="td text-slate-400" colspan="6" x-text="$store.app.t('no_data')"></td></tr></tbody></table></div>

    <div x-show="form" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
      <form class="card my-8 w-full max-w-2xl space-y-3" @submit.prevent="create()" x-show="form">
        <h2 class="text-lg font-semibold">New booking</h2>
        <p x-show="formErr" x-text="formErr" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
        <p x-show="form && !services.length" class="text-sm text-slate-500">Create a service first under Tours &amp; Activities → Services.</p>
        <template x-if="form"><div class="grid gap-3 sm:grid-cols-2">
          <label class="text-sm font-medium sm:col-span-2">Service<select class="input mt-1" x-model="form.catalog_item_id" @change="onService()"><template x-for="s in services" :key="s.id"><option :value="s.id" x-text="s.name + ' · ' + s.kind"></option></template></select></label>
          <label class="text-sm font-medium" x-show="departures.length">Departure<select class="input mt-1" x-model="form.departure_id" @change="requote()"><option value="">Private / own date</option><template x-for="d in departures" :key="d.id"><option :value="d.id" x-text="d.date.slice(0,10) + ' ' + (d.start_time || '') + ' · ' + d.available + ' seats'"></option></template></select></label>
          <label class="text-sm font-medium" x-show="!form.departure_id"><span x-text="$store.app.t('date')"></span><input type="date" class="input mt-1" x-model="form.date" required></label>
          <label class="text-sm font-medium">Adults<input type="number" min="1" class="input mt-1" x-model.number="form.pax_adults" @input="requote()"></label>
          <label class="text-sm font-medium">Children<input type="number" min="0" class="input mt-1" x-model.number="form.pax_children" @input="requote()"></label>
          <label class="text-sm font-medium" x-show="service && ['per_day','per_night','per_hour'].includes(service.pricing_model)">Days / hours<input type="number" min="1" step="0.5" class="input mt-1" x-model.number="form.units" @input="requote()"></label>
          <label class="text-sm font-medium"><span x-text="$store.app.t('name') + ' *'"></span><input class="input mt-1" x-model="form.customer_name" required></label>
          <label class="text-sm font-medium"><span x-text="$store.app.t('phone')"></span><input class="input mt-1" x-model="form.customer_phone"></label>
          <label class="text-sm font-medium sm:col-span-2">Pickup location<input class="input mt-1" x-model="form.pickup_location"></label>
          <label class="text-sm font-medium sm:col-span-2">Notes<textarea class="input mt-1" rows="2" x-model="form.notes"></textarea></label>
        </div></template>
        <div x-show="quote" class="rounded-lg bg-emerald-50 p-3 text-sm"><template x-for="l in quote?.lines || []"><div class="flex justify-between"><span x-text="l.description"></span><span x-text="money(l.line_total)"></span></div></template>
          <div class="mt-1 flex justify-between border-t pt-1 font-bold"><span x-text="$store.app.t('total')"></span><span x-text="money(quote?.total)"></span></div></div>
        <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="form = null" x-text="$store.app.t('cancel')"></button><button class="btn btn-primary" :disabled="busy || !quote" x-text="$store.app.t('save')"></button></div>
      </form>
    </div>

    <div x-show="view" x-cloak class="fixed inset-0 z-40 flex justify-end bg-black/40" @click.self="view = null">
      <div class="h-full w-full max-w-lg space-y-4 overflow-y-auto bg-white p-5 shadow-xl" x-show="view"><template x-if="view"><div class="space-y-4">
        <div class="flex items-start justify-between"><div><h2 class="text-xl font-bold" x-text="view.number"></h2><p class="text-sm text-slate-500" x-text="view.customer_name + ' · ' + view.date.slice(0,10) + ' · ' + (view.pax_adults + view.pax_children) + ' pax'"></p></div><button class="btn btn-secondary btn-sm" @click="view = null" x-text="$store.app.t('close')"></button></div>
        <span class="badge capitalize" :class="badge(view.status)" x-text="view.status.replace('_',' ')"></span>
        <table class="min-w-full text-sm"><tbody class="divide-y divide-slate-100"><template x-for="l in view.lines" :key="l.id"><tr><td class="py-1" x-text="Number(l.quantity) + ' × ' + l.description"></td><td class="py-1 text-right" x-text="money(l.line_total)"></td></tr></template></tbody></table>
        <p class="text-sm"><span x-text="$store.app.t('total')"></span> <b x-text="money(view.total)"></b> · <span x-text="$store.app.t('balance')"></span> <b x-text="money(view.balance)"></b></p>
        <div class="flex flex-wrap gap-2"><template x-for="s in { inquiry: ['confirmed','cancelled'], confirmed: ['in_progress','cancelled','no_show'], paid: ['in_progress','cancelled','no_show'], in_progress: ['completed'] }[view.status] || []" :key="s"><button class="btn btn-secondary btn-sm capitalize" @click="status(s)" x-text="s.replace('_',' ')"></button></template></div>
        <div class="card space-y-2" x-show="['confirmed','paid','in_progress','inquiry'].includes(view.status) && $store.app.can('tourism.assign')">
          <h3 class="font-semibold">Guide / vehicle</h3>
          <ul class="text-sm"><template x-for="a in view.assignments" :key="a.id"><li x-text="resName(a.resource_id) + ' (' + a.role + ')'"></li></template></ul>
          <div class="flex gap-2"><select class="input" x-model="view.resource_id"><option value="">—</option><template x-for="r in resources" :key="r.id"><option :value="r.id" x-text="r.name + ' · ' + r.kind"></option></template></select><button class="btn btn-secondary" @click="assign()" :disabled="!view.resource_id">Assign</button></div>
        </div>
        <div class="card space-y-2" x-show="view.balance > 0 && !['cancelled','no_show','completed'].includes(view.status)">
          <h3 class="font-semibold">Payment</h3>
          <div class="flex gap-2"><select class="input w-36" x-model="view.pay.method_type"><template x-for="m in methods.filter(m => m.type !== 'room_charge')" :key="m.key"><option :value="m.type" x-text="m.name"></option></template></select><input class="input" type="number" x-model="view.pay.amount"><button class="btn btn-primary" @click="pay()" x-text="$store.app.t('pay')"></button></div>
          <input class="input" x-show="view.pay.method_type === 'momo'" placeholder="MoMo number 2507…" x-model="view.pay.payer_phone">
        </div>
      </div></template></div>
    </div>
  </div>
</template>

<template x-if="$store.app.route.page === 'tours.departures'">
  <div x-data="departuresPage()">
    <div class="mb-4 flex flex-wrap items-center gap-3 print:hidden"><h1 class="text-2xl font-bold" x-text="$store.app.t('departures')"></h1>
      <input type="date" class="input w-auto" x-model="from" @change="load()"><div class="flex-1"></div><button class="btn btn-primary" @click="newForm()" x-show="$store.app.can('tourism.create')">+ Departure</button></div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white print:hidden"><table class="min-w-full divide-y divide-slate-200">
      <thead class="bg-slate-50"><tr><th class="th" x-text="$store.app.t('date')"></th><th class="th">Tour</th><th class="th">Seats</th><th class="th" x-text="$store.app.t('status')"></th><th class="th"></th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="d in rows" :key="d.id"><tr>
        <td class="td" x-text="d.date.slice(0,10) + ' ' + (d.start_time || '')"></td><td class="td" x-text="tourName(d.catalog_item_id)"></td><td class="td" x-text="d.booked + ' / ' + d.capacity"></td><td class="td capitalize" x-text="d.status"></td>
        <td class="td text-right"><button class="btn btn-secondary btn-sm" @click="showManifest(d)">Manifest</button> <button class="btn btn-secondary btn-sm" x-show="d.status === 'open'" @click="setStatus(d, 'closed')">Close</button> <button class="btn btn-secondary btn-sm" x-show="d.status !== 'open' && d.status !== 'completed'" @click="setStatus(d, 'open')">Reopen</button></td></tr></template>
      <tr x-show="!loading && !rows.length"><td class="td text-slate-400" colspan="5" x-text="$store.app.t('no_data')"></td></tr></tbody></table></div>
    <div x-show="form" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"><form class="card w-full max-w-md space-y-3" @submit.prevent="save()" x-show="form"><h2 class="text-lg font-semibold">New departure</h2>
      <p x-show="err" x-text="err" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"></p>
      <template x-if="form"><div class="grid gap-3"><label class="text-sm font-medium">Tour<select class="input mt-1" x-model="form.catalog_item_id"><template x-for="t in tours" :key="t.id"><option :value="t.id" x-text="t.name"></option></template></select></label>
        <div class="grid grid-cols-2 gap-3"><label class="text-sm font-medium">Date<input type="date" class="input mt-1" x-model="form.date" required></label><label class="text-sm font-medium">Time<input type="time" class="input mt-1" x-model="form.start_time"></label></div>
        <label class="text-sm font-medium">Seats<input type="number" min="1" class="input mt-1" x-model="form.capacity" required></label><label class="text-sm font-medium">Meeting point<input class="input mt-1" x-model="form.meeting_point"></label></div></template>
      <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="form = null" x-text="$store.app.t('cancel')"></button><button class="btn btn-primary" x-text="$store.app.t('save')"></button></div></form></div>
    <div x-show="manifest" x-cloak class="fixed inset-0 z-40 overflow-y-auto bg-white p-6 print:static print:p-0"><template x-if="manifest"><div class="mx-auto max-w-3xl space-y-4">
      <div class="flex items-start justify-between"><div><h2 class="text-2xl font-bold">Manifest · <span x-text="manifest.tour?.name"></span></h2><p class="text-slate-600" x-text="manifest.departure.date.slice(0,10) + ' ' + (manifest.departure.start_time || '') + ' · ' + manifest.departure.booked + '/' + manifest.departure.capacity + ' seats'"></p></div>
        <div class="flex gap-2 print:hidden"><button class="btn btn-secondary" @click="print()">Print</button><button class="btn btn-secondary" @click="manifest = null" x-text="$store.app.t('close')"></button></div></div>
      <table class="min-w-full text-sm"><thead><tr><th class="th">Booking</th><th class="th">Customer</th><th class="th">Pax</th><th class="th">Phone</th><th class="th">Balance</th></tr></thead><tbody class="divide-y"><template x-for="b in manifest.bookings" :key="b.id"><tr><td class="td" x-text="b.number"></td><td class="td" x-text="b.customer_name"></td><td class="td" x-text="b.pax"></td><td class="td" x-text="b.customer_phone"></td><td class="td" x-text="money(b.balance)"></td></tr></template></tbody></table>
      <h3 class="font-semibold">Participants</h3><ul class="text-sm"><template x-for="p in manifest.participants" :key="p.id"><li x-text="p.name + (p.nationality ? ' · ' + p.nationality : '') + (p.id_number ? ' · ' + p.id_number : '')"></li></template></ul>
      <h3 class="font-semibold">Guides &amp; vehicles</h3><ul class="text-sm"><template x-for="r in manifest.resources" :key="r.id"><li x-text="r.name + ' (' + r.kind + ')' + (r.plate ? ' ' + r.plate : '')"></li></template></ul>
    </div></template></div>
  </div>
</template>
@endverbatim
