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
      <div class="mb-3" x-show="$store.ui.favItems().length"><p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">★ <span x-text="$store.app.t('pinned')"></span></p>
        <template x-for="f in $store.ui.favItems()" :key="f.route"><a :href="'#/' + f.route" class="block rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-100" x-text="f.title"></a></template></div>
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
      <header class="sticky top-0 z-10 flex items-center gap-2 border-b border-slate-200 bg-white px-3 py-2 print:hidden">
        <button class="btn btn-secondary lg:hidden" @click="$store.app.nav = !$store.app.nav" aria-label="Menu">☰</button>
        <!-- search / command palette -->
        <button class="flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm text-slate-500 hover:border-emerald-400 sm:max-w-md" @click="$store.ui.openPalette()">
          <span>🔍</span><span class="truncate" x-text="$store.app.t('search_all')"></span><kbd class="ml-auto hidden rounded border border-slate-300 px-1.5 text-[10px] sm:inline">Ctrl K</kbd></button>
        <div class="flex-1"></div>
        <!-- pin current page -->
        <button class="btn btn-secondary btn-sm" :title="$store.ui.isFav($store.app.route.path) ? 'Unpin' : 'Pin this page'" @click="$store.ui.toggleFav($store.app.route.path)" x-show="$store.app.route.path !== ''"><span x-text="$store.ui.isFav($store.app.route.path) ? '★' : '☆'"></span></button>
        <!-- workspaces -->
        <div class="relative" x-show="$store.app.ctx.workspaces && $store.app.ctx.workspaces.length > 1">
          <button class="btn btn-secondary btn-sm" @click="$store.ui.wsMenu = !$store.ui.wsMenu"><span x-text="$store.app.t('workspaces')"></span> ▾</button>
          <div class="absolute right-0 z-30 mt-1 w-64 rounded-xl border border-slate-200 bg-white p-1 shadow-xl" x-show="$store.ui.wsMenu" @click.outside="$store.ui.wsMenu = false">
            <template x-for="w in $store.app.ctx.workspaces" :key="w.key"><a :href="w.url" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><b x-text="w.label"></b><span class="block text-xs text-slate-500" x-text="w.desc"></span></a></template></div></div>
        <!-- property -->
        <template x-if="$store.app.ctx.properties.length > 1">
          <select class="input w-auto" :value="$store.app.propertyId" @change="$store.app.setProperty($event.target.value)" aria-label="Property">
            <option value="" x-text="$store.app.t('all_properties')"></option>
            <template x-for="p in $store.app.ctx.properties" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
          </select>
        </template>
        <!-- notifications -->
        <div class="relative">
          <button class="btn btn-secondary btn-sm relative" @click="$store.ui.bell = !$store.ui.bell; $store.ui.loadNotifs()" aria-label="Notifications">🔔<span x-show="$store.ui.notifs.length" class="absolute -right-1.5 -top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white" x-text="$store.ui.notifs.length"></span></button>
          <div class="absolute right-0 z-30 mt-1 max-h-[70vh] w-80 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl" x-show="$store.ui.bell" @click.outside="$store.ui.bell = false">
            <p class="border-b border-slate-100 px-4 py-2 text-sm font-semibold" x-text="$store.app.t('notifications')"></p>
            <template x-for="n in $store.ui.notifs" :key="n.title"><button class="flex w-full gap-3 border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50" @click="$store.ui.open(n)"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="$store.ui.sevClass(n.severity)"></span><span><b class="block text-sm" x-text="n.title"></b><span class="text-xs text-slate-500" x-text="n.detail"></span></span></button></template>
            <p class="px-4 py-6 text-center text-sm text-slate-500" x-show="!$store.ui.notifs.length" x-text="$store.app.t('no_alerts')"></p></div></div>
        <!-- appearance -->
        <div class="relative">
          <button class="btn btn-secondary btn-sm" @click="$store.ui.panel = !$store.ui.panel" :title="$store.app.t('appearance')">🎨</button>
          <div class="absolute right-0 z-30 mt-1 w-72 space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-xl" x-show="$store.ui.panel" @click.outside="$store.ui.panel = false">
            <p class="font-semibold" x-text="$store.app.t('appearance')"></p>
            <div><p class="mb-1 text-xs font-medium text-slate-500">Mode</p><div class="flex gap-1"><template x-for="m in ['auto','light','dark']"><button class="btn btn-sm flex-1 capitalize" :class="$store.ui.prefs.theme === m ? 'btn-primary' : 'btn-secondary'" @click="$store.ui.setPref({ theme: m })" x-text="m"></button></template></div></div>
            <div><p class="mb-1 text-xs font-medium text-slate-500">Accent colour</p><div class="flex gap-2"><template x-for="a in $store.ui.accents" :key="a[0]"><button class="h-7 w-7 rounded-full ring-offset-2" :class="$store.ui.prefs.accent === a[0] ? 'ring-2 ring-slate-700' : ''" :style="'background:' + a[1]" @click="$store.ui.setPref({ accent: a[0] })" :aria-label="a[0]"></button></template></div></div>
            <div><p class="mb-1 text-xs font-medium text-slate-500">Density</p><div class="flex gap-1"><template x-for="d in ['comfortable','compact']"><button class="btn btn-sm flex-1 capitalize" :class="$store.ui.prefs.density === d ? 'btn-primary' : 'btn-secondary'" @click="$store.ui.setPref({ density: d })" x-text="d"></button></template></div></div>
            <div><p class="mb-1 text-xs font-medium text-slate-500">Text size</p><div class="flex gap-1"><template x-for="s in ['sm','md','lg','xl']"><button class="btn btn-sm flex-1 uppercase" :class="$store.ui.prefs.textSize === s ? 'btn-primary' : 'btn-secondary'" @click="$store.ui.setPref({ textSize: s })" x-text="s"></button></template></div></div>
            <div><p class="mb-1 text-xs font-medium text-slate-500" x-text="$store.app.t('language')"></p><select class="input" :value="$store.app.locale" @change="$store.app.setLocale($event.target.value)"><option value="en">English</option><option value="fr">Français</option><option value="rw">Kinyarwanda</option></select></div>
          </div></div>
        <span class="hidden text-sm text-slate-600 lg:inline" x-text="$store.app.ctx.user.name"></span>
        <button class="btn btn-secondary btn-sm" @click="$store.app.logout()" x-text="$store.app.t('logout')"></button>
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

    <!-- command palette -->
    <div class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-[12vh]" x-show="$store.ui.pal.open" @click.self="$store.ui.pal.open = false">
      <div class="w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl" x-show="$store.ui.pal.open">
        <input id="palette-input" class="w-full border-0 border-b border-slate-200 bg-transparent px-4 py-3 text-base focus:outline-none focus:ring-0" :placeholder="$store.app.t('search_all')" x-model="$store.ui.pal.q" @input="$store.ui.onQuery()"
               @keydown.arrow-down.prevent="$store.ui.move(1)" @keydown.arrow-up.prevent="$store.ui.move(-1)" @keydown.enter.prevent="$store.ui.choose()" autocomplete="off">
        <ul class="max-h-80 overflow-y-auto p-1">
          <template x-for="(r, i) in $store.ui.results()" :key="r.kind + r.title + i"><li><button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm" :class="$store.ui.pal.idx === i ? 'bg-emerald-50 text-emerald-800' : ''" @mouseenter="$store.ui.pal.idx = i" @click="$store.ui.choose(i)">
            <span class="w-16 shrink-0 text-[10px] font-semibold uppercase text-slate-400" x-text="r.kind"></span><span class="flex-1 truncate" x-text="r.title"></span><span class="truncate text-xs text-slate-400" x-text="r.group"></span></button></li></template>
          <li class="px-4 py-6 text-center text-sm text-slate-500" x-show="!$store.ui.results().length">No matches</li></ul>
        <p class="border-t border-slate-100 px-4 py-2 text-[11px] text-slate-400">↑↓ to move · Enter to open · Esc to close</p></div></div>

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
