@extends('layouts.site')
@section('title', 'Features · Edusmart Hospitality')
@section('body')
<section class="mx-auto max-w-7xl px-4 py-14">
  <h1 class="text-4xl font-extrabold">Everything in one platform</h1>
  <p class="mt-3 max-w-2xl text-lg text-slate-600">Switch on only the modules you need. Everything works on Android and in the browser, in three languages.</p>
  <div class="mt-10 space-y-6">
    @foreach ([
      ['rooms', '🛏', 'Rooms & reservations', ['Room types, rooms and floors', 'Seasonal, weekday and meal-plan rates', 'Live availability with overbooking protection', 'Check-in with guest ID, check-out with balance check', 'Guest bills (folios) with voids, never deletions', 'Automatic night audit; rooms-by-days timeline', 'Guest profiles with stay history and loyalty tier']],
      ['pos', '🍽', 'Restaurant, bar & shop sales', ['Touch sales terminal with table floor plan', 'Kitchen and bar display with live timers', 'Split and merge bills, notes, tips, promo codes', 'Manager PIN for voids and discounts', 'Cash shifts with float and cash-up variance', 'Cash, MTN MoMo, card, bank, charge-to-room', 'Barcode scanning; Bluetooth receipt printing']],
      ['tourism', '🦍', 'Tours & activities', ['Tours, activities, transfers, rentals, spa, events, packages', 'Price per person, per group, per day or hour', 'Child prices and group-size discounts', 'Departures with seat limits and manifests', 'Guides and vehicles with clash detection', 'Deposits, balances and payment tracking', 'Tour calendar and charge-to-room']],
      ['inventory', '📦', 'Inventory & purchasing', ['Stock ledger that cannot be silently edited', 'Suppliers, purchase orders, goods receipts', 'Recipes deduct ingredients on every sale', 'Stocktakes, waste, low-stock alerts']],
      ['staff', '👥', 'Staff & housekeeping', ['Employees, rosters, leave approvals', 'Clock in/out from the phone, even offline', 'Payroll export from attendance', 'Room status board, cleaning and maintenance tasks with photos']],
      ['finance', '📈', 'Reports & finance', ['Dashboard with charts and trends', 'Sales by day, item, payment method and cashier', 'Occupancy, ADR and RevPAR', 'Expenses, profit, cash reconciliation, CSV export']],
      ['custom', '🧩', 'Make it yours', ['Your own custom fields on guests, bookings, items…', 'Rename any label per language', 'Edit VAT, service charge, tourism levy and payment methods', 'Create roles with a permission matrix', 'Dark mode, accent colours, compact density']],
      ['safe', '🔒', 'Safe by design', ['Each company isolated from every other', 'Two-factor sign-in, remote device revoke', 'Audit log of every sensitive action', 'Export or erase your data at any time']],
    ] as [$id, $i, $t, $items])
      <div id="{{ $id }}" class="grid gap-6 rounded-2xl border border-slate-200 p-6 lg:grid-cols-[16rem_1fr]"><div><div class="text-3xl">{{ $i }}</div><h2 class="mt-2 text-xl font-bold">{{ $t }}</h2></div>
        <ul class="grid gap-x-8 gap-y-2 text-slate-700 sm:grid-cols-2">@foreach ($items as $x)<li class="flex gap-2"><span class="text-emerald-600">✓</span><span>{{ $x }}</span></li>@endforeach</ul></div>
    @endforeach
  </div>
  <div class="mt-10 text-center"><a href="/register" class="btn btn-primary px-6 py-3">Start free trial</a></div>
</section>
@endsection
