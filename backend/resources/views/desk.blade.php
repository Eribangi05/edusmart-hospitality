<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Front desk · Edusmart Hospitality</title>
  @vite(['resources/css/app.css', 'resources/js/desk.js'])
</head>
<body class="min-h-full bg-slate-100 text-slate-900 antialiased">
@verbatim
<div x-data="desk()" x-cloak class="flex min-h-screen flex-col">
  <header class="sticky top-0 z-20 border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-[1500px] items-center gap-3 px-4 py-2.5">
      <a href="/workspaces" class="btn btn-secondary btn-sm">☰</a><p class="font-bold text-emerald-700">Front desk</p><p class="hidden text-sm text-slate-500 sm:block" x-text="tenant"></p>
      <nav class="ml-4 flex gap-1 rounded-xl bg-slate-100 p-1 text-sm font-medium">
        <template x-for="t in tabs" :key="t.k"><button class="rounded-lg px-3 py-1.5" :class="tab === t.k ? 'bg-white text-emerald-700 shadow' : 'text-slate-600'" @click="tab = t.k" x-text="t.l" x-show="t.show()"></button></template>
      </nav>
      <div class="flex-1"></div>
      <button class="btn btn-primary btn-sm" @click="$dispatch('new-res')" x-show="mod('rooms')">+ Reservation</button>
      <button class="btn btn-secondary btn-sm" @click="theme()">◐</button>
    </div>
  </header>

  <main class="mx-auto w-full max-w-[1500px] flex-1 p-4">
    <!-- TODAY -->
    <section x-show="tab === 'today'" x-data="deskToday()" @changed.window="load()">
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <template x-for="t in [['arrivals','Arrivals'],['in_house','In house'],['departures','Departures'],['upcoming','Next 7 days']]" :key="t[0]"><button class="rounded-xl px-4 py-2 text-sm font-semibold" :class="sub === t[0] ? 'bg-emerald-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200'" @click="sub = t[0]"><span x-text="t[1]"></span> <span class="ml-1 rounded-full bg-black/10 px-2 py-0.5 text-xs" x-text="counts()[t[0]]"></span></button></template>
        <button class="btn btn-secondary btn-sm ml-auto" @click="load()">Refresh</button>
      </div>
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <template x-for="r in list()" :key="r.id"><article class="cursor-pointer rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:ring-emerald-400" @click="$dispatch('open-res', { id: r.id })">
          <div class="flex items-start justify-between"><div><p class="font-semibold" x-text="r.guest || 'Guest'"></p><p class="text-xs text-slate-500" x-text="r.number + ' · ' + r.type"></p></div>
            <span class="badge" :class="r.room ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'" x-text="r.room ? 'Room ' + r.room : 'No room'"></span></div>
          <p class="mt-2 text-sm text-slate-600" x-text="r.check_in + ' → ' + r.check_out + ' · ' + r.adults + (r.children ? '+' + r.children : '') + ' guests'"></p>
          <div class="mt-2 flex flex-wrap gap-1.5 text-xs"><span x-show="!r.has_id && sub === 'arrivals'" class="badge bg-amber-100 text-amber-800">ID needed</span><span x-show="r.balance > 0" class="badge bg-red-100 text-red-700" x-text="'Owes ' + money(r.balance)"></span><span x-show="r.balance <= 0 && sub === 'departures'" class="badge bg-emerald-100 text-emerald-800">Settled</span><span x-show="r.notes" class="badge bg-sky-100 text-sky-800">Note</span></div></article></template>
      </div>
      <p x-show="!loading && !list().length" class="rounded-2xl bg-white p-10 text-center text-slate-500">Nothing here. Enjoy the calm ☕</p>
    </section>

    <!-- TIMELINE -->
    <section x-show="tab === 'timeline'" x-data="timeline()" @changed.window="load()">
@endverbatim
@include('partials.timeline')
@verbatim
    </section>

    <!-- TOURS -->
    <section x-show="tab === 'tours'" x-data="tourCalendar()">
@endverbatim
@include('partials.tourcal')
@verbatim
    </section>

    <!-- GUESTS -->
    <section x-show="tab === 'guests'" x-data="guestLookup()" class="grid gap-4 lg:grid-cols-[22rem_1fr]">
      <div class="space-y-2"><input class="input" placeholder="Search name, phone, email or ID number…" x-model="q" @input.debounce.300ms="search()">
        <template x-for="g in rows" :key="g.id"><button class="block w-full rounded-xl bg-white p-3 text-left shadow-sm ring-1 ring-slate-200 hover:ring-emerald-400" @click="open(g.id)"><b x-text="g.name"></b><span class="block text-xs text-slate-500" x-text="[g.phone, g.nationality].filter(Boolean).join(' · ')"></span></button></template>
        <p x-show="q.length >= 2 && !rows.length && !loading" class="text-sm text-slate-500">No guests found.</p></div>
      <div><p x-show="!p" class="rounded-2xl bg-white p-10 text-center text-slate-500">Pick a guest to see their history</p>
        <template x-if="p"><div class="space-y-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
          <div class="flex items-start justify-between"><div><h2 class="text-xl font-bold" x-text="p.guest.name"></h2><p class="text-sm text-slate-500" x-text="[p.guest.phone, p.guest.email, p.guest.nationality].filter(Boolean).join(' · ')"></p></div><span class="badge" :class="tierClass(p.stats.tier)" x-text="p.stats.tier"></span></div>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4"><div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Stays</p><p class="text-xl font-bold" x-text="p.stats.stays"></p></div><div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Nights</p><p class="text-xl font-bold" x-text="p.stats.nights"></p></div><div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Tours</p><p class="text-xl font-bold" x-text="p.stats.tours"></p></div><div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Spend</p><p class="text-xl font-bold" x-text="money(p.stats.spend)"></p></div></div>
          <button class="btn btn-primary" @click="$dispatch('new-res', { guest_id: p.guest.id, guest_name: p.guest.name })">New reservation for this guest</button>
          <div x-show="p.reservations.length"><p class="mb-1 text-xs font-semibold uppercase text-slate-400">Stays</p><template x-for="r in p.reservations" :key="r.id"><button class="flex w-full justify-between border-b border-slate-100 py-1.5 text-left text-sm" @click="$dispatch('open-res', { id: r.id })"><span x-text="r.number + ' · ' + r.check_in + ' → ' + r.check_out"></span><span class="text-slate-500" x-text="r.status + ' · ' + money(r.total)"></span></button></template></div>
          <div x-show="p.bookings.length"><p class="mb-1 text-xs font-semibold uppercase text-slate-400">Tours &amp; activities</p><template x-for="b in p.bookings" :key="b.id"><p class="flex justify-between border-b border-slate-100 py-1.5 text-sm"><span x-text="b.number + ' · ' + b.date"></span><span class="text-slate-500" x-text="b.status + ' · ' + money(b.total)"></span></p></template></div>
        </div></template></div>
    </section>
  </main>

@endverbatim
@include('partials.res-modals')
@verbatim
</div>
@endverbatim
</body>
</html>
