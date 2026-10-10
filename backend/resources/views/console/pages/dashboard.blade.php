@verbatim
<template x-if="$store.app.route.page === 'dashboard'">
  <div x-data="dashboardPage()">
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <h1 class="text-2xl font-bold" x-text="$store.app.t('dashboard')"></h1>
      <input type="date" class="input w-auto" x-model="date" @change="load()">
      <button class="btn btn-secondary" @click="load()" x-text="$store.app.t('refresh')"></button>
    </div>
    <p x-show="loading" class="text-slate-500" x-text="$store.app.t('loading')"></p>
    <template x-if="d">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card"><p class="text-sm text-slate-500">Collected today</p><p class="mt-1 text-2xl font-bold" x-text="money(d.collected.total)"></p>
          <ul class="mt-2 space-y-0.5 text-xs text-slate-500"><template x-for="[m, v] in methods()" :key="m"><li class="flex justify-between"><span x-text="m.replace('_',' ')"></span><span x-text="money(v)"></span></li></template></ul></div>
        <div class="card"><p class="text-sm text-slate-500" x-text="$store.app.t('orders')"></p><p class="mt-1 text-2xl font-bold" x-text="d.orders.paid_today"></p>
          <p class="mt-1 text-xs text-slate-500"><span x-text="d.orders.open"></span> open · sales <span x-text="money(d.orders.sales_today)"></span></p></div>
        <template x-if="$store.app.hasModule('rooms')">
          <div class="card"><p class="text-sm text-slate-500" x-text="$store.app.t('rooms')"></p><p class="mt-1 text-2xl font-bold"><span x-text="d.rooms.occupied"></span>/<span x-text="d.rooms.total"></span> <span class="text-base font-normal text-slate-500" x-text="d.rooms.occupancy_pct + '%'"></span></p>
            <p class="mt-1 text-xs text-slate-500"><span x-text="d.rooms.arrivals"></span> arrivals · <span x-text="d.rooms.departures"></span> departures · <span x-text="d.rooms.dirty"></span> dirty</p></div>
        </template>
        <template x-if="$store.app.hasModule('tourism')">
          <div class="card"><p class="text-sm text-slate-500" x-text="$store.app.t('tours')"></p><p class="mt-1 text-2xl font-bold" x-text="d.tourism.bookings_today"></p><p class="mt-1 text-xs text-slate-500"><span x-text="d.tourism.pax_today"></span> guests today</p></div>
        </template>
        <template x-if="d.low_stock !== undefined"><a href="#/inventory/stock" class="card block"><p class="text-sm text-slate-500">Low stock</p><p class="mt-1 text-2xl font-bold" :class="d.low_stock ? 'text-red-600' : ''" x-text="d.low_stock"></p></a></template>
        <template x-if="$store.app.hasModule('housekeeping')"><a href="#/crud/tasks" class="card block"><p class="text-sm text-slate-500" x-text="$store.app.t('tasks')"></p><p class="mt-1 text-2xl font-bold" x-text="d.tasks_open"></p></a></template>
        <a href="#/pos/shifts" class="card block"><p class="text-sm text-slate-500" x-text="$store.app.t('shifts')"></p><p class="mt-1 text-2xl font-bold" x-text="d.open_shifts"></p></a>
      </div>
    </template>
  </div>
</template>
@endverbatim
