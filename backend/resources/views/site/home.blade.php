@extends('layouts.site')
@section('title', 'Edusmart Hospitality: hotels, restaurants, bars, shops and tours')
@section('body')
<section class="relative overflow-hidden bg-gradient-to-b from-emerald-50 to-white">
  <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-16 lg:grid-cols-2 lg:py-24">
    <div>
      <span class="badge bg-emerald-100 text-emerald-800">Made for Rwanda · MTN MoMo built in</span>
      <h1 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl">Run your lodge, restaurant, shop and tours from one place.</h1>
      <p class="mt-5 max-w-xl text-lg text-slate-600">Reservations, sales, inventory, staff, housekeeping and tour bookings. A native Android app that keeps working offline, a manager console, a touch sales terminal, a kitchen display and a front desk, each designed for its job.</p>
      <div class="mt-8 flex flex-wrap gap-3"><a href="/register" class="btn btn-primary px-6 py-3 text-base">Start 30-day free trial</a><a href="/features" class="btn btn-secondary px-6 py-3 text-base">See features</a></div>
      <p class="mt-4 text-sm text-slate-500">English · Français · Kinyarwanda &nbsp;·&nbsp; Android &amp; web &nbsp;·&nbsp; Your data, your rules</p>
    </div>
    <!-- product preview built in CSS so there is no image to load -->
    <div class="relative">
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
        <div class="mb-3 flex items-center justify-between"><p class="font-semibold">Today · Lake Kivu Lodge</p><span class="badge bg-emerald-100 text-emerald-800">Live</span></div>
        <div class="grid grid-cols-3 gap-3 text-center">
          <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Collected</p><p class="text-lg font-bold">RWF 1.2M</p></div>
          <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Occupancy</p><p class="text-lg font-bold">83%</p></div>
          <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Tours today</p><p class="text-lg font-bold">6</p></div>
        </div>
        <div class="mt-4 flex h-28 items-end gap-2">@foreach ([40, 55, 35, 70, 60, 85, 95] as $h)<div class="flex-1 rounded-t bg-emerald-500" style="height: {{ $h }}%"></div>@endforeach</div>
        <div class="mt-3 grid grid-cols-6 gap-1.5 text-center text-xs font-semibold">@foreach (['bg-emerald-100 text-emerald-800', 'bg-rose-100 text-rose-800', 'bg-amber-100 text-amber-800', 'bg-emerald-100 text-emerald-800', 'bg-rose-100 text-rose-800', 'bg-orange-100 text-orange-800'] as $i => $c)<div class="rounded-lg py-2 {{ $c }}">10{{ $i + 1 }}</div>@endforeach</div>
      </div>
      <div class="absolute -bottom-5 -left-3 hidden rounded-xl border border-slate-200 bg-white p-3 shadow-lg sm:block"><p class="text-xs text-slate-500">Offline sales queued</p><p class="font-bold text-emerald-700">3 → synced ✓</p></div>
    </div>
  </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-14">
  <h2 class="text-center text-3xl font-bold">One platform, a workspace for every job</h2>
  <p class="mx-auto mt-2 max-w-2xl text-center text-slate-600">After sign-in, each person lands in the screen built for their work, with a layout that fits the device in their hand.</p>
  <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ([
      ['📊', 'Manager console', 'Dashboards, charts, setup, users, roles, reports, billing. Dark mode and your own accent colour.'],
      ['🧾', 'Sales terminal', 'Full-screen touch POS with a table floor plan, quick notes, promo codes, split bills and MoMo.'],
      ['🍳', 'Kitchen & bar display', 'Live tickets by station with timers that turn amber and red, sounds for new orders.'],
      ['🛎', 'Front desk', 'Arrivals, departures, in-house guests, a rooms-by-days timeline, tours calendar and guest profiles.'],
      ['📱', 'Staff app', 'Housekeepers and guides see only their tasks and trips and can clock in with one tap.'],
      ['🤖', 'Android, offline-first', 'Orders, cash payments, tasks and clock-ins are saved on the phone and sync when the network returns.'],
    ] as [$i, $t, $d])
      <div class="rounded-2xl border border-slate-200 p-6 transition hover:-translate-y-0.5 hover:shadow-lg"><div class="text-3xl">{{ $i }}</div><h3 class="mt-3 font-semibold">{{ $t }}</h3><p class="mt-1 text-sm text-slate-600">{{ $d }}</p></div>
    @endforeach
  </div>
</section>

<section class="bg-slate-50 py-14">
  <div class="mx-auto max-w-7xl px-4">
    <h2 class="text-center text-3xl font-bold">Built for how you actually work</h2>
    <div class="mt-10 grid gap-5 md:grid-cols-3">
      @foreach ([
        ['Hotels, lodges & guest houses', 'Room types, seasonal and weekend rates, overbooking protection, guest bills, night audit, housekeeping.', '/solutions#hotels'],
        ['Restaurants, bars & shops', 'Menu and recipes that deduct stock, tables, kitchen tickets, shifts and cash-up, barcode selling.', '/solutions#restaurants'],
        ['Tour operators', 'Gorilla treks, canoe trips, transfers, car hire and packages with departures, seats, guides and manifests.', '/solutions#tours'],
      ] as [$t, $d, $u])
        <a href="{{ $u }}" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 hover:ring-emerald-400"><h3 class="font-semibold text-emerald-700">{{ $t }}</h3><p class="mt-2 text-sm text-slate-600">{{ $d }}</p><p class="mt-4 text-sm font-medium text-emerald-700">Learn more →</p></a>
      @endforeach
    </div>
  </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-14">
  <h2 class="text-center text-3xl font-bold">Start in an afternoon</h2>
  <div class="mt-10 grid gap-6 md:grid-cols-3">
    @foreach ([['1', 'Create your company', 'Pick the modules you need. A guided checklist shows what to set up next.'], ['2', 'Make it yours', 'Add your rooms, menu and tours, your taxes, your roles, even your own custom fields.'], ['3', 'Go live', 'Sign your team in on Android or the web. Take cash and mobile money from the first sale.']] as [$n, $t, $d])
      <div class="flex gap-4"><div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-600 font-bold text-white">{{ $n }}</div><div><h3 class="font-semibold">{{ $t }}</h3><p class="mt-1 text-sm text-slate-600">{{ $d }}</p></div></div>
    @endforeach
  </div>
</section>

<section class="mx-auto max-w-7xl px-4"><div class="rounded-3xl bg-emerald-700 px-6 py-12 text-center text-white"><h2 class="text-3xl font-bold">Ready to try it?</h2><p class="mt-2 text-emerald-100">30 days free. No card needed. Cancel anytime.</p>
  <div class="mt-6 flex flex-wrap justify-center gap-3"><a href="/register" class="btn bg-white px-6 py-3 text-emerald-800 hover:bg-emerald-50">Create your company</a><a href="/contact" class="btn border border-white/60 px-6 py-3 text-white hover:bg-white/10">Talk to us</a></div></div></section>
@endsection
