@verbatim
      <div class="mb-3 flex items-center gap-2"><button class="btn btn-secondary btn-sm" @click="move(-1)">‹</button><h2 class="min-w-40 text-center text-lg font-bold" x-text="title()"></h2><button class="btn btn-secondary btn-sm" @click="move(1)">›</button></div>
      <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
        <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
          <div class="mb-1 grid grid-cols-7 text-center text-xs font-semibold text-slate-400"><template x-for="d in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']"><span x-text="d"></span></template></div>
          <div class="grid grid-cols-7 gap-1">
            <template x-for="(c, i) in cells()" :key="i"><div>
              <template x-if="c"><button class="h-20 w-full rounded-xl border p-1.5 text-left text-xs transition hover:border-emerald-400" :class="[sel === c ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200', isToday(c) ? 'ring-2 ring-emerald-300' : '']" @click="sel = c">
                <b x-text="Number(c.slice(8))"></b><span class="mt-1 block text-[10px] text-sky-700" x-show="deps(c).length" x-text="deps(c).length + ' departure' + (deps(c).length > 1 ? 's' : '')"></span><span class="block text-[10px] text-emerald-700" x-show="bks(c).length" x-text="bks(c).length + ' booking' + (bks(c).length > 1 ? 's' : '') + ' · ' + pax(c) + ' pax'"></span></button></template></div></template>
          </div></div>
        <aside class="space-y-3"><h3 class="font-semibold" x-text="new Date(sel + 'T00:00:00').toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long' })"></h3>
          <template x-for="d in deps(sel)" :key="d.id"><div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200"><p class="font-semibold" x-text="name(d.catalog_item_id)"></p><p class="text-sm text-slate-500" x-text="(d.start_time || '') + ' · ' + d.booked + '/' + d.capacity + ' seats · ' + d.status"></p><button class="btn btn-secondary btn-sm mt-2" @click="showManifest(d)">Manifest</button></div></template>
          <template x-for="b in bks(sel)" :key="b.id"><div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200"><p class="font-semibold" x-text="b.customer_name"></p><p class="text-sm text-slate-500" x-text="b.number + ' · ' + (b.start_time || '') + ' · ' + (b.pax_adults + b.pax_children) + ' pax · ' + b.status"></p></div></template>
          <p x-show="!deps(sel).length && !bks(sel).length" class="rounded-xl bg-white p-6 text-center text-sm text-slate-500">Nothing on this day</p></aside>
      </div>
      <div x-show="manifest" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-3"><div class="card max-h-[90vh] w-full max-w-lg space-y-2 overflow-y-auto" x-show="manifest"><template x-if="manifest"><div>
        <div class="flex justify-between"><h3 class="font-bold" x-text="name(manifest.departure.catalog_item_id) + ' · ' + manifest.departure.date.slice(0,10)"></h3><button class="btn btn-secondary btn-sm" @click="manifest = null">✕</button></div>
        <p class="text-sm text-slate-500" x-text="manifest.departure.booked + '/' + manifest.departure.capacity + ' seats · ' + (manifest.departure.meeting_point || '')"></p>
        <template x-for="b in manifest.bookings" :key="b.id"><p class="border-b border-slate-100 py-1 text-sm" x-text="b.customer_name + ' · ' + b.pax + ' pax · balance ' + money(b.balance)"></p></template>
        <p class="pt-2 text-xs font-semibold uppercase text-slate-400">Participants</p><template x-for="p in manifest.participants" :key="p.id"><p class="text-sm" x-text="p.name + ' ' + (p.nationality || '') + ' ' + (p.id_number || '')"></p></template>
        <button class="btn btn-primary mt-2 w-full" @click="printManifest()">Print manifest</button></div></template></div></div>
    @endverbatim
