@extends('layouts.site')
@section('title', 'Pricing · Edusmart Hospitality')
@section('body')
<section class="mx-auto max-w-7xl px-4 py-14">
  <h1 class="text-center text-4xl font-extrabold">Simple pricing in Rwandan francs</h1>
  <p class="mx-auto mt-3 max-w-2xl text-center text-lg text-slate-600">Every plan starts with a 30-day free trial. Pay monthly with MTN MoMo. Change plan or cancel anytime.</p>
  <div class="mt-10 grid gap-6 md:grid-cols-3">
    @foreach ($plans as $i => $p)
      <div class="relative rounded-2xl border {{ $p->key === 'business' ? 'border-emerald-500 shadow-lg' : 'border-slate-200' }} bg-white p-6">
        @if ($p->key === 'business')<span class="badge absolute -top-3 left-6 bg-emerald-600 text-white">Most popular</span>@endif
        <h2 class="text-xl font-bold">{{ $p->name }}</h2>
        <p class="mt-3"><span class="text-4xl font-extrabold">{{ number_format($p->price_monthly) }}</span> <span class="text-slate-500">RWF / month</span></p>
        <ul class="mt-5 space-y-2 text-sm">@foreach (($p->modules ?? []) as $m)<li class="flex gap-2"><span class="text-emerald-600">✓</span><span class="capitalize">{{ str_replace('_', ' ', $m) }}</span></li>@endforeach</ul>
        <a href="/register" class="btn {{ $p->key === 'business' ? 'btn-primary' : 'btn-secondary' }} mt-6 w-full">Start free trial</a>
      </div>
    @endforeach
  </div>
  <div class="mt-14 grid gap-6 md:grid-cols-2" x-data="{ open: 0 }">
    <div><h2 class="text-2xl font-bold">Questions</h2>
      @foreach ([
        ['Do I need internet all the time?', 'No. On Android, orders, cash payments, tasks and clock-ins are saved on the phone and sync automatically. Reservations and mobile-money payments need a connection.'],
        ['Can I pay with MTN MoMo?', 'Yes, both for your subscription and for taking payments from your own customers.'],
        ['Can I use it for tours and a hotel together?', 'Yes. Turn on the modules you need; a tour can even be charged to a guest\'s room bill.'],
        ['Can I change taxes, labels and fields?', 'Yes. VAT, service charge, tourism levy, labels, roles and custom fields are all yours to set.'],
        ['What happens to my data if I leave?', 'You can export everything at any time and ask us to erase it.'],
      ] as $n => [$q, $a])
        <div class="border-b border-slate-200 py-3"><button class="flex w-full justify-between text-left font-medium" @click="open = open === {{ $n }} ? -1 : {{ $n }}">{{ $q }} <span x-text="open === {{ $n }} ? '−' : '+'"></span></button><p x-show="open === {{ $n }}" x-cloak class="mt-2 text-sm text-slate-600">{{ $a }}</p></div>
      @endforeach
    </div>
    <div class="rounded-2xl bg-emerald-50 p-6"><h3 class="text-xl font-bold">Need something special?</h3><p class="mt-2 text-slate-600">Several properties, a hotel group, or a tour company with your own workflow? Tell us what you need.</p><a href="/contact" class="btn btn-primary mt-4">Contact us</a></div>
  </div>
</section>
@endsection
