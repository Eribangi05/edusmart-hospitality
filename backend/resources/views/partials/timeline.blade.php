@verbatim
      <div class="mb-3 flex flex-wrap items-center gap-2"><button class="btn btn-secondary btn-sm" @click="shift(-7)">‹ Week</button><button class="btn btn-secondary btn-sm" @click="goToday()">Today</button><button class="btn btn-secondary btn-sm" @click="shift(7)">Week ›</button>
        <input type="date" class="input w-auto" x-model="from" @change="load()"><select class="input w-auto" x-model.number="days" @change="load()"><option value="7">7 days</option><option value="14">14 days</option><option value="21">21 days</option><option value="31">31 days</option></select>
        <div class="ml-auto flex gap-2 text-xs"><span class="badge bg-emerald-500 text-white">In house</span><span class="badge bg-sky-500 text-white">Confirmed</span><span class="badge bg-amber-400">Pending</span><span class="badge bg-slate-300">Checked out</span></div></div>
      <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200" x-show="t">
        <div class="min-w-[900px]">
          <div class="grid border-b border-slate-200" style="grid-template-columns: 9rem 1fr"><div class="px-3 py-2 text-xs font-semibold uppercase text-slate-400">Room</div>
            <div class="grid" :style="'grid-template-columns: repeat(' + days + ', minmax(0, 1fr))'"><template x-for="d in dayList()" :key="d"><div class="border-l border-slate-100 py-1 text-center text-[11px]" :class="isToday(d) ? 'bg-emerald-50 font-bold text-emerald-700' : isWeekend(d) ? 'bg-slate-50' : ''"><span x-text="label(d)"></span><span class="block text-[9px] text-slate-400" x-text="occupancyPct(d) + '%'"></span></div></template></div></div>
          <template x-for="rm in t?.rooms || []" :key="rm.id"><div class="grid border-b border-slate-100" style="grid-template-columns: 9rem 1fr">
            <div class="flex items-center gap-2 px-3 py-2 text-sm"><span class="h-2.5 w-2.5 rounded-full" :class="hk(rm)"></span><b x-text="rm.number"></b><span class="truncate text-xs text-slate-400" x-text="rm.type"></span></div>
            <div class="relative grid items-center py-1.5" :style="'grid-template-columns: repeat(' + days + ', minmax(0, 1fr)); row-gap: 0'">
              <template x-for="(d, i) in dayList()" :key="d"><button class="absolute inset-y-0 border-l border-slate-100 hover:bg-emerald-50/70" :class="isToday(d) ? 'bg-emerald-50/60' : isWeekend(d) ? 'bg-slate-50/70' : ''" :style="'left:' + (i * 100 / days) + '%; width:' + (100 / days) + '%'" @click="$dispatch('new-res', { room_id: rm.id, check_in: d })" title="New reservation here"></button></template>
              <template x-for="r in rm.reservations" :key="r.id"><button class="relative z-[1] mx-0.5 truncate rounded-md px-2 py-1 text-left text-xs font-medium shadow-sm" :class="barClass(r)" :style="span(r)" @click="$dispatch('open-res', { id: r.id })" x-text="r.guest || r.number"></button></template>
            </div></div></template>
        </div>
      </div>
      <div x-show="t && t.unassigned.length" class="mt-4 rounded-2xl bg-amber-50 p-4"><p class="mb-2 text-sm font-semibold text-amber-900">Reservations without a room</p>
        <div class="flex flex-wrap gap-2"><template x-for="r in t?.unassigned || []" :key="r.id"><button class="rounded-lg bg-white px-3 py-1.5 text-sm shadow-sm" @click="$dispatch('open-res', { id: r.id })" x-text="(r.guest || r.number) + ' · ' + r.check_in + ' → ' + r.check_out"></button></template></div></div>
    @endverbatim
