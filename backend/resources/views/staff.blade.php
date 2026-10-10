<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#059669">
  <title>My shift · Edusmart Hospitality</title>
  @vite(['resources/css/app.css', 'resources/js/staff.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
@verbatim
<div x-data="staff()" x-cloak class="mx-auto flex h-full max-w-md flex-col bg-slate-50 shadow-xl">
  <header class="bg-emerald-600 px-4 pb-5 pt-4 text-white">
    <div class="flex items-center justify-between"><p class="text-sm text-emerald-100" x-text="tenant"></p><div class="flex gap-2"><a href="/workspaces" class="rounded-lg bg-white/15 px-2.5 py-1 text-sm">☰</a><button class="rounded-lg bg-white/15 px-2.5 py-1 text-sm" @click="logout()">Log out</button></div></div>
    <h1 class="mt-1 text-2xl font-bold" x-text="greeting"></h1>
    <p class="text-sm text-emerald-100" x-text="summary()"></p>
  </header>
  <p x-show="error" x-text="error" class="bg-red-600 px-4 py-2 text-sm text-white" role="alert" @click="error = ''"></p>
  <main class="min-h-0 flex-1 overflow-y-auto p-4">
    <!-- tasks -->
    <section x-show="tab === 'tasks'" class="space-y-3">
      <p x-show="!tasks.length" class="rounded-xl bg-white p-6 text-center text-slate-500 shadow-sm">🎉 No tasks right now</p>
      <template x-for="t in tasks" :key="t.id"><article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200" :class="t.priority === 'urgent' ? 'ring-2 ring-red-400' : ''">
        <div class="flex items-start justify-between gap-2"><div><h3 class="font-semibold" x-text="t.title"></h3><p class="text-xs text-slate-500" x-text="roomLabel(t)"></p></div>
          <span class="badge" :class="{ 'bg-red-100 text-red-700': t.priority === 'urgent', 'bg-amber-100 text-amber-800': t.priority === 'high', 'bg-slate-100 text-slate-600': ['normal','low'].includes(t.priority) }" x-text="t.priority"></span></div>
        <p x-show="t.description" class="mt-2 text-sm text-slate-600" x-text="t.description"></p>
        <div class="mt-3 grid grid-cols-2 gap-2"><button class="btn btn-secondary py-3" x-show="t.status === 'pending'" @click="act(t, 'start')">Start</button>
          <button class="btn btn-primary py-3" :class="t.status === 'pending' ? '' : 'col-span-2'" @click="act(t, 'complete')">Done ✓</button></div></article></template>
    </section>
    <!-- trips -->
    <section x-show="tab === 'trips'" class="space-y-3">
      <p x-show="!trips.length" class="rounded-xl bg-white p-6 text-center text-slate-500 shadow-sm">No trips assigned to you</p>
      <template x-for="b in trips" :key="b.id"><article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-semibold uppercase text-emerald-700" x-text="b.date + (b.start_time ? ' · ' + b.start_time : '')"></p><h3 class="font-semibold" x-text="b.service"></h3>
        <p class="text-sm" x-text="b.customer_name + ' · ' + b.pax + ' pax'"></p><p class="text-sm text-slate-500" x-show="b.pickup_location" x-text="'📍 ' + b.pickup_location"></p><p class="text-sm text-slate-500" x-show="b.notes" x-text="b.notes"></p>
        <a x-show="b.customer_phone" :href="'tel:' + b.customer_phone" class="btn btn-secondary mt-3 w-full">📞 Call customer</a></article></template>
    </section>
    <!-- clock -->
    <section x-show="tab === 'clock'" class="space-y-4 text-center">
      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><p class="text-5xl font-extrabold tabular-nums" x-text="time"></p><p class="mt-1 text-slate-500" x-text="date"></p>
        <p class="mt-4 text-sm" :class="lastType === 'in' ? 'text-emerald-700' : 'text-slate-500'" x-text="lastText()"></p></div>
      <button class="w-full rounded-2xl bg-emerald-600 py-5 text-xl font-bold text-white shadow active:scale-95" @click="clockEvent('in')">Clock in</button>
      <button class="w-full rounded-2xl bg-slate-800 py-5 text-xl font-bold text-white shadow active:scale-95" @click="clockEvent('out')">Clock out</button>
      <p x-show="clockMsg" x-text="clockMsg" class="text-sm text-slate-600"></p>
    </section>
    <!-- me -->
    <section x-show="tab === 'me'" class="space-y-3">
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><p class="font-semibold" x-text="user.name"></p><p class="text-sm text-slate-500" x-text="user.email"></p><p class="mt-1 text-xs text-slate-500" x-text="'Roles: ' + (user.roles || []).join(', ')"></p></div>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 space-y-3"><p class="font-semibold">Appearance</p>
        <div class="flex gap-2"><template x-for="m in ['auto','light','dark']"><button class="btn btn-sm flex-1 capitalize" :class="prefs.theme === m ? 'btn-primary' : 'btn-secondary'" @click="setPref({ theme: m })" x-text="m"></button></template></div>
        <div class="flex gap-2"><template x-for="a in accents"><button class="h-8 w-8 rounded-full ring-offset-2" :class="prefs.accent === a[0] ? 'ring-2 ring-slate-800' : ''" :style="'background:' + a[1]" @click="setPref({ accent: a[0] })" :aria-label="a[0]"></button></template></div>
        <div class="flex gap-2"><template x-for="s in ['sm','md','lg','xl']"><button class="btn btn-sm flex-1 uppercase" :class="prefs.textSize === s ? 'btn-primary' : 'btn-secondary'" @click="setPref({ textSize: s })" x-text="s"></button></template></div></div>
    </section>
  </main>
  <nav class="grid border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]" :style="'grid-template-columns: repeat(' + tabs().length + ', 1fr)'">
    <template x-for="t in tabs()" :key="t.k"><button class="relative py-2.5 text-center text-xs font-medium" :class="tab === t.k ? 'text-emerald-700' : 'text-slate-500'" @click="tab = t.k"><span class="block text-xl" x-text="t.i"></span><span x-text="t.l"></span><span x-show="t.n" class="absolute right-6 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[10px] text-white" x-text="t.n"></span></button></template>
  </nav>
</div>
@endverbatim
</body>
</html>
