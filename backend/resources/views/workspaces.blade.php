<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Choose a workspace · Edusmart Hospitality</title>
  @vite(['resources/css/app.css', 'resources/js/workspaces.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
@verbatim
<main x-data="launcher()" x-cloak class="mx-auto max-w-5xl px-4 py-10">
  <header class="mb-8 flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-600 font-bold text-white">E</span>
    <div><p class="text-sm text-slate-500" x-text="tenant"></p><h1 class="text-2xl font-bold" x-text="greeting"></h1></div><div class="flex-1"></div>
    <button class="btn btn-secondary btn-sm" @click="logout()">Log out</button></header>
  <p x-show="loading" class="text-slate-500">Loading…</p>
  <p x-show="!loading && !items.length" class="rounded-xl bg-white p-6 text-slate-600 ring-1 ring-slate-200">Your role has no workspace yet. Ask the owner to give you a role.</p>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <template x-for="w in items" :key="w.key">
      <a :href="w.url" class="group rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-emerald-400">
        <div class="mb-3 grid h-12 w-12 place-items-center rounded-xl bg-emerald-50 text-2xl" x-text="icon(w.icon)"></div>
        <h2 class="font-semibold" x-text="w.label"></h2><p class="mt-1 text-sm text-slate-600" x-text="w.desc"></p>
        <p class="mt-4 text-sm font-medium text-emerald-700">Open →</p></a>
    </template>
  </div>
  <p class="mt-8 text-center text-xs text-slate-400">Tip: you can switch workspace at any time from the menu in the top bar.</p>
</main>
@endverbatim
</body>
</html>
