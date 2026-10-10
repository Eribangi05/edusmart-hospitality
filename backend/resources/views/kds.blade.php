<!doctype html>
<html lang="en" class="h-full dark">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Kitchen & bar display · Edusmart Hospitality</title>
  @vite(['resources/css/app.css', 'resources/js/kds.js'])
</head>
<body class="h-full overflow-hidden bg-slate-950 text-slate-100 antialiased">
@verbatim
<div x-data="kds()" x-cloak class="flex h-full flex-col">
  <header class="flex items-center gap-3 border-b border-slate-800 bg-slate-900 px-4 py-3">
    <a href="/workspaces" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm hover:bg-slate-700">☰</a>
    <h1 class="text-lg font-bold">Kitchen &amp; bar</h1>
    <div class="flex gap-1 rounded-lg bg-slate-800 p-1 text-sm">
      <template x-for="s in ['all','kitchen','bar']" :key="s"><button class="rounded-md px-3 py-1 capitalize" :class="station === s ? 'bg-emerald-600 text-white' : 'text-slate-300'" @click="station = s; load()" x-text="s"></button></template>
    </div>
    <div class="flex gap-3 text-sm"><span class="rounded bg-rose-500/20 px-2 py-0.5 text-rose-300">New <b x-text="count('new')"></b></span><span class="rounded bg-amber-500/20 px-2 py-0.5 text-amber-300">Cooking <b x-text="count('cooking')"></b></span><span class="rounded bg-emerald-500/20 px-2 py-0.5 text-emerald-300">Ready <b x-text="count('ready')"></b></span></div>
    <div class="flex-1"></div>
    <p x-show="offline" class="text-sm text-amber-300">⚠ connection lost, showing last data</p>
    <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm hover:bg-slate-700" @click="sound = !sound; beep(true)" x-text="sound ? '🔔 Sound on' : '🔕 Muted'"></button>
    <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm hover:bg-slate-700" @click="fullscreen()">⛶</button>
    <span class="tabular-nums text-slate-400" x-text="clock"></span>
  </header>
  <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 p-3 md:grid-cols-3">
    <template x-for="col in cols" :key="col.k">
      <section class="flex min-h-0 flex-col rounded-2xl bg-slate-900/60 p-2">
        <h2 class="px-2 py-1 text-sm font-semibold uppercase tracking-wide" :class="col.cls" x-text="col.label"></h2>
        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-1">
          <template x-for="t in tickets(col.k)" :key="t.order.id">
            <article class="kds-ticket rounded-xl bg-slate-800 p-3 shadow" :class="ageClass(t)">
              <header class="mb-2 flex items-center justify-between"><div><p class="text-lg font-bold" x-text="t.order.number"></p><p class="text-xs text-slate-400" x-text="where(t.order)"></p></div>
                <p class="text-2xl font-extrabold tabular-nums" :class="ageText(t)" x-text="mins(t) + '′'"></p></header>
              <ul class="space-y-1.5">
                <template x-for="i in t.items" :key="i.id"><li class="flex items-start gap-2 rounded-lg bg-slate-900/60 px-2 py-1.5" :class="i.status === 'ready' ? 'opacity-60' : ''">
                  <span class="text-xl font-extrabold text-emerald-400" x-text="Number(i.quantity) + '×'"></span>
                  <div class="flex-1"><p class="text-base font-semibold leading-tight" x-text="i.name"></p><p x-show="i.notes" class="text-sm font-medium text-amber-300" x-text="'✎ ' + i.notes"></p></div>
                  <button class="rounded bg-slate-700 px-2 py-1 text-xs" @click="step(i)" x-text="{ sent: 'Start', preparing: 'Ready', ready: 'Served' }[i.status]"></button></li></template>
              </ul>
              <button class="mt-3 w-full rounded-lg py-2.5 text-base font-bold text-white" :class="col.btn" @click="advance(t)" x-text="col.action"></button>
            </article>
          </template>
          <p x-show="!tickets(col.k).length" class="py-10 text-center text-sm text-slate-600">—</p>
        </div>
      </section>
    </template>
  </div>
</div>
@endverbatim
</body>
</html>
