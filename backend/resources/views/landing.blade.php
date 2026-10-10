@extends('layouts.public')
@section('title', 'Edusmart Hospitality: hotels, restaurants, bars, shops and tours')
@section('body')
<header class="mx-auto flex max-w-6xl items-center justify-between px-4 py-5">
  <a href="/" class="text-lg font-semibold text-emerald-700">Edusmart Hospitality</a>
  <nav class="flex items-center gap-4 text-sm">
    <a href="https://edusmartconsult.com" class="hidden text-slate-600 hover:text-slate-900 sm:inline">edusmartconsult.com</a>
    <a href="/login" class="text-slate-700 hover:text-slate-900">Log in</a>
    <a href="/register" class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Start free trial</a>
  </nav>
</header>
<main>
  <section class="mx-auto max-w-6xl px-4 py-14 text-center">
    <h1 class="mx-auto max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl">One platform for hotels, restaurants, shops and tour operators in Rwanda</h1>
    <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-600">Reservations, POS, inventory, staff, housekeeping, tours and reports on an Android app that keeps working offline, plus a web console for managers. English, Français, Kinyarwanda. MTN MoMo built in.</p>
    <div class="mt-8 flex justify-center gap-3">
      <a href="/register" class="rounded-lg bg-emerald-600 px-6 py-3 font-medium text-white hover:bg-emerald-700">Start 30-day free trial</a>
      <a href="/login" class="rounded-lg border border-slate-300 bg-white px-6 py-3 font-medium hover:bg-slate-100">Log in</a>
    </div>
  </section>
  <section class="mx-auto grid max-w-6xl gap-4 px-4 pb-16 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([
      ['Rooms &amp; reservations','Availability calendar, rate plans, check-in/out, guest folios and night audit.'],
      ['Restaurant, bar &amp; shop POS','Tables, kitchen and bar tickets, split bills, shifts, MTN MoMo and charge-to-room.'],
      ['Tours &amp; activities','Departures, group pricing, packages, guides and vehicles, manifests, deposits.'],
      ['Inventory &amp; recipes','Stock ledger, purchase orders, stocktakes and automatic ingredient deduction.'],
      ['Staff &amp; housekeeping','Rosters, clock-in, leave, payroll export, room status board and tasks.'],
      ['Reports &amp; finance','Occupancy, ADR, RevPAR, sales by item, cash-up and profit.'],
      ['Make it yours','Custom fields, taxes, price rules, roles, labels and modules are all configurable per company.'],
      ['Works offline','The Android app queues sales and tasks and syncs when the network returns.'],
    ] as [$t,$d])
      <div class="rounded-xl border border-slate-200 bg-white p-5"><h3 class="font-semibold">{!! $t !!}</h3><p class="mt-2 text-sm text-slate-600">{{ $d }}</p></div>
    @endforeach
  </section>
</main>
<footer class="border-t border-slate-200 py-6 text-center text-sm text-slate-500">
  &copy; {{ date('Y') }} Edusmart Consult · <a class="underline" href="/privacy">Privacy</a> · <a class="underline" href="/terms">Terms</a>
</footer>
@endsection
