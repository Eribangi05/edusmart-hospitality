<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Edusmart Hospitality</title>
  @vite(['resources/css/app.css', 'resources/js/console/main.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased print:bg-white">
@verbatim
<div x-data x-cloak class="min-h-full">
  <template x-if="!$store.app.ready"><div class="flex h-screen items-center justify-center text-slate-500">Loading…</div></template>
  <template x-if="$store.app.ready">
  <div class="flex min-h-screen">
    <!-- sidebar -->
    <aside class="fixed inset-y-0 left-0 z-30 w-64 transform overflow-y-auto border-r border-slate-200 bg-white p-3 transition print:hidden lg:static lg:translate-x-0"
           :class="$store.app.nav ? 'translate-x-0' : '-translate-x-full'">
      <a href="#/" class="mb-4 block px-2 text-lg font-semibold text-emerald-700" x-text="$store.app.ctx.tenant.name"></a>
      <nav aria-label="Main">
        <template x-for="g in $store.app.visibleNav()" :key="g.label">
          <div class="mb-3">
            <template x-if="!g.items"><a :href="'#/' + g.route" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium hover:bg-slate-100" :class="$store.app.route.path === g.route ? 'bg-emerald-50 text-emerald-800' : ''" x-text="$store.app.t(g.label)"></a></template>
            <template x-if="g.items">
              <div>
                <p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400" x-text="$store.app.t(g.label)"></p>
                <template x-for="i in g.items" :key="i.route">
                  <a :href="'#/' + i.route" class="block rounded-lg px-2 py-1.5 text-sm hover:bg-slate-100" :class="$store.app.route.path === i.route ? 'bg-emerald-50 font-medium text-emerald-800' : 'text-slate-700'" x-text="$store.app.t(i.label)"></a>
                </template>
              </div>
            </template>
          </div>
        </template>
      </nav>
    </aside>
    <div class="fixed inset-0 z-20 bg-black/30 lg:hidden" x-show="$store.app.nav" @click="$store.app.nav = false"></div>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="sticky top-0 z-10 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-2 print:hidden">
        <button class="btn btn-secondary lg:hidden" @click="$store.app.nav = !$store.app.nav" aria-label="Menu">☰</button>
        <div class="flex-1"></div>
        <template x-if="$store.app.ctx.properties.length > 1">
          <select class="input w-auto" :value="$store.app.propertyId" @change="$store.app.setProperty($event.target.value)" aria-label="Property">
            <option value="" x-text="$store.app.t('all_properties')"></option>
            <template x-for="p in $store.app.ctx.properties" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
          </select>
        </template>
        <select class="input w-auto" :value="$store.app.locale" @change="$store.app.setLocale($event.target.value)" aria-label="Language">
          <option value="en">English</option><option value="fr">Français</option><option value="rw">Kinyarwanda</option>
        </select>
        <span class="hidden text-sm text-slate-600 sm:inline" x-text="$store.app.ctx.user.name"></span>
        <button class="btn btn-secondary" @click="$store.app.logout()" x-text="$store.app.t('logout')"></button>
      </header>

      <div x-show="$store.app.banner" class="bg-amber-100 px-4 py-2 text-sm text-amber-900 print:hidden">
        <span x-text="$store.app.banner"></span> <a href="#/settings/billing" class="underline">→</a>
      </div>

      <main class="flex-1 p-4 lg:p-6" id="main">
@endverbatim
        @include('console.pages.dashboard')
        @include('console.pages.crud')
        @include('console.pages.rooms')
        @include('console.pages.pos')
        @include('console.pages.tourism')
        @include('console.pages.inventory')
        @include('console.pages.staff')
        @include('console.pages.finance')
        @include('console.pages.settings')
@verbatim
      </main>
    </div>

    <!-- toasts -->
    <div class="fixed bottom-4 right-4 z-50 space-y-2 print:hidden" aria-live="polite">
      <template x-for="x in $store.app.toasts" :key="x.id">
        <div class="max-w-sm rounded-lg px-4 py-3 text-sm text-white shadow-lg" :class="{ 'bg-emerald-600': x.kind === 'ok', 'bg-red-600': x.kind === 'error', 'bg-slate-800': x.kind === 'info' }" x-text="x.msg"></div>
      </template>
    </div>
  </div>
  </template>
</div>
@endverbatim
</body>
</html>
