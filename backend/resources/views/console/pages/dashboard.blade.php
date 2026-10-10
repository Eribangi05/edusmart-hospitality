@verbatim
<template x-if="$store.app.route.page === 'dashboard'">
  <div x-data="dashboardPage()" class="space-y-5">
    <div class="flex flex-wrap items-end gap-3">
      <div><p class="text-sm text-slate-500" x-text="new Date().toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long' })"></p><h1 class="text-2xl font-bold" x-text="greeting()"></h1></div>
      <div class="flex-1"></div>
      <input type="date" class="input w-auto" x-model="date" @change="load()"><button class="btn btn-secondary" @click="load()" x-text="$store.app.t('refresh')"></button>
    </div>

    <!-- workspace shortcuts -->
    <div class="flex flex-wrap gap-2" x-show="$store.app.ctx.workspaces && $store.app.ctx.workspaces.length > 1">
      <template x-for="w in $store.app.ctx.workspaces.filter(x => x.key !== 'console')" :key="w.key"><a :href="w.url" class="rounded-xl bg-white px-4 py-2.5 text-sm font-medium shadow-sm ring-1 ring-slate-200 hover:ring-emerald-400"><span x-text="w.label"></span> →</a></template>
    </div>

    <!-- setup guide -->
    <div class="card" x-show="onboarding && onboarding.done < onboarding.total">
      <div class="flex items-center justify-between"><h2 class="font-semibold" x-text="$store.app.t('setup_guide')"></h2><span class="text-sm text-slate-500" x-text="onboarding?.done + ' / ' + onboarding?.total"></span></div>
      <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-2 rounded-full bg-emerald-500 transition-all" :style="'width:' + progress() + '%'"></div></div>
      <ul class="mt-3 grid gap-x-6 gap-y-1.5 sm:grid-cols-2"><template x-for="s in onboarding?.steps || []" :key="s.key"><li><a :href="'#/' + s.route" class="flex items-center gap-2 text-sm" :class="s.done ? 'text-slate-400 line-through' : 'text-slate-700 hover:text-emerald-700'"><span class="grid h-5 w-5 place-items-center rounded-full text-[11px]" :class="s.done ? 'bg-emerald-500 text-white' : 'border border-slate-300'" x-text="s.done ? '✓' : ''"></span><span x-text="s.title"></span></a></li></template></ul>
    </div>

    <p x-show="loading" class="text-slate-500" x-text="$store.app.t('loading')"></p>
    <template x-if="d">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card"><p class="text-sm text-slate-500">Collected today</p><p class="mt-1 text-2xl font-bold" x-text="money(d.collected.total)"></p>
          <p class="mt-1 text-xs" x-show="delta() !== null" :class="delta() >= 0 ? 'text-emerald-600' : 'text-red-600'" x-text="(delta() >= 0 ? '▲ ' : '▼ ') + Math.abs(delta()) + '% vs previous 7 days'"></p>
          <ul class="mt-2 space-y-0.5 text-xs text-slate-500"><template x-for="[m, v] in methods()" :key="m"><li class="flex justify-between"><span class="capitalize" x-text="m.replace('_',' ')"></span><span x-text="money(v)"></span></li></template></ul></div>
        <div class="card"><p class="text-sm text-slate-500" x-text="$store.app.t('orders')"></p><p class="mt-1 text-2xl font-bold" x-text="d.orders.paid_today"></p><p class="mt-1 text-xs text-slate-500"><span x-text="d.orders.open"></span> open · sales <span x-text="money(d.orders.sales_today)"></span></p></div>
        <template x-if="$store.app.hasModule('rooms')"><div class="card"><p class="text-sm text-slate-500" x-text="$store.app.t('rooms')"></p><p class="mt-1 text-2xl font-bold"><span x-text="d.rooms.occupied"></span>/<span x-text="d.rooms.total"></span> <span class="text-base font-normal text-slate-500" x-text="d.rooms.occupancy_pct + '%'"></span></p>
          <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-1.5 rounded-full bg-emerald-500" :style="'width:' + d.rooms.occupancy_pct + '%'"></div></div>
          <p class="mt-1 text-xs text-slate-500"><span x-text="d.rooms.arrivals"></span> arrivals · <span x-text="d.rooms.departures"></span> departures · <span x-text="d.rooms.dirty"></span> dirty</p></div></template>
        <template x-if="$store.app.hasModule('tourism')"><div class="card"><p class="text-sm text-slate-500" x-text="$store.app.t('tours')"></p><p class="mt-1 text-2xl font-bold" x-text="d.tourism.bookings_today"></p><p class="mt-1 text-xs text-slate-500"><span x-text="d.tourism.pax_today"></span> guests today</p></div></template>
        <template x-if="d.low_stock !== undefined"><a href="#/inventory/stock" class="card block"><p class="text-sm text-slate-500">Low stock</p><p class="mt-1 text-2xl font-bold" :class="d.low_stock ? 'text-red-600' : ''" x-text="d.low_stock"></p></a></template>
        <template x-if="$store.app.hasModule('housekeeping')"><a href="#/crud/tasks" class="card block"><p class="text-sm text-slate-500" x-text="$store.app.t('tasks')"></p><p class="mt-1 text-2xl font-bold" x-text="d.tasks_open"></p></a></template>
        <a href="#/pos/shifts" class="card block"><p class="text-sm text-slate-500" x-text="$store.app.t('shifts')"></p><p class="mt-1 text-2xl font-bold" x-text="d.open_shifts"></p></a>
      </div>
    </template>

    <!-- charts -->
    <div class="grid gap-4 lg:grid-cols-2" x-show="trends">
      <div class="card"><div class="mb-2 flex items-center justify-between"><h2 class="font-semibold">Money collected</h2><span class="text-xs text-slate-500" x-text="$store.app.t('trends')"></span></div><div x-html="$chart.bars(revenueBars())"></div></div>
      <div class="card" x-show="$store.app.hasModule('rooms')"><div class="mb-2 flex items-center justify-between"><h2 class="font-semibold">Occupancy %</h2><span class="text-xs text-slate-500" x-text="$store.app.t('trends')"></span></div><div x-html="$chart.line(occupancyLine(), { suffix: '%' })"></div></div>
      <div class="card" x-show="topItems().length"><h2 class="mb-3 font-semibold">Best sellers</h2>
        <ul class="space-y-2"><template x-for="i in topItems()" :key="i.label"><li><div class="flex justify-between text-sm"><span x-text="i.label"></span><span class="text-slate-500" x-text="i.qty + ' sold · ' + money(i.value)"></span></div><div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-2 rounded-full bg-emerald-500" :style="'width:' + (100 * i.value / topItems()[0].value) + '%'"></div></div></li></template></ul></div>
      <div class="card" x-show="mix().length"><h2 class="mb-3 font-semibold">How customers pay</h2><div x-html="$chart.donut(mix())"></div></div>
      <div class="card lg:col-span-2" x-show="hours().some(v => v)"><h2 class="mb-3 font-semibold">Busiest hours</h2><div x-html="$chart.heat(hours())"></div></div>
    </div>

    <!-- alerts + activity -->
    <div class="grid gap-4 lg:grid-cols-2">
      <div class="card"><h2 class="mb-2 font-semibold" x-text="$store.app.t('notifications')"></h2>
        <template x-for="n in $store.ui.notifs.slice(0, 6)" :key="n.title"><a :href="'#/' + n.route" class="flex gap-3 border-b border-slate-100 py-2 text-sm last:border-0"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="$store.ui.sevClass(n.severity)"></span><span><b x-text="n.title"></b><span class="block text-xs text-slate-500" x-text="n.detail"></span></span></a></template>
        <p x-show="!$store.ui.notifs.length" class="py-4 text-center text-sm text-slate-500" x-text="$store.app.t('no_alerts')"></p></div>
      <div class="card" x-show="activity.length"><h2 class="mb-2 font-semibold" x-text="$store.app.t('activity')"></h2>
        <ul class="space-y-1.5"><template x-for="a in activity.slice(0, 8)" :key="a.id"><li class="flex justify-between gap-3 text-sm"><span><b x-text="a.by"></b> <span class="text-slate-500 capitalize" x-text="label(a.action)"></span></span><span class="shrink-0 text-xs text-slate-400" x-text="ago(a.at)"></span></li></template></ul></div>
    </div>
  </div>
</template>
@endverbatim
