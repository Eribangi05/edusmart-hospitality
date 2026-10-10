@extends('layouts.site')
@section('title', 'Solutions · Edusmart Hospitality')
@section('body')
<section class="mx-auto max-w-7xl px-4 py-14">
  <h1 class="text-4xl font-extrabold">Solutions for every kind of hospitality business</h1>
  @foreach ([
    ['hotels', 'Hotels, lodges & guest houses', 'Keep every room sold and every guest happy.', ['See the whole month at a glance on the rooms-by-days timeline', 'Weekend and high-season rates apply automatically', 'Room status flows from checkout to housekeeping to inspected', 'Guest bills include room, restaurant, bar and tour charges', 'Arrivals and departures with outstanding balances flagged'], 'Front desk workspace · Manager console'],
    ['restaurants', 'Restaurants, bars & shops', 'Faster service, fewer losses.', ['Full-screen touch terminal for waiters and cashiers', 'Tickets reach the kitchen and bar instantly', 'Recipes deduct ingredients; low stock alerts you early', 'Voids and discounts need a manager; every till is counted at shift end', 'Sell by barcode; accept MoMo, cash, card, or charge to a room'], 'Sales terminal · Kitchen & bar display'],
    ['tours', 'Tour operators', 'From inquiry to manifest without spreadsheets.', ['Price per person with child rates and group discounts', 'Seat-limited departures; overbooking is impossible', 'Assign guides and vehicles; clashes are blocked', 'Print the manifest with participants and passport numbers', 'Take a deposit now and the balance later'], 'Front desk workspace · Tours calendar · Staff app for guides'],
  ] as [$id, $t, $tag, $pts, $ws])
    <div id="{{ $id }}" class="mt-10 grid items-center gap-8 rounded-3xl bg-slate-50 p-8 lg:grid-cols-2">
      <div><h2 class="text-2xl font-bold text-emerald-700">{{ $t }}</h2><p class="mt-1 text-lg text-slate-600">{{ $tag }}</p><p class="mt-4 text-sm text-slate-500">Workspaces: {{ $ws }}</p><a href="/register" class="btn btn-primary mt-5">Try it free</a></div>
      <ul class="space-y-3">@foreach ($pts as $p)<li class="flex gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><span class="text-emerald-600">✓</span><span>{{ $p }}</span></li>@endforeach</ul>
    </div>
  @endforeach
</section>
@endsection
