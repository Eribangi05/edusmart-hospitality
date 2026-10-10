@verbatim
<template x-if="$store.app.route.page === 'rooms.board'">
  <div x-data="roomBoardPage()">
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <h1 class="text-2xl font-bold" x-text="$store.app.t('board')"></h1>
      <input type="date" class="input w-auto" x-model="date" @change="load()">
      <div class="flex gap-2 text-xs">
        <span class="badge bg-emerald-100 text-emerald-800">Vacant <b x-text="counts().vacant"></b></span>
        <span class="badge bg-rose-100 text-rose-800">Occupied <b x-text="counts().occupied"></b></span>
        <span class="badge bg-amber-100 text-amber-800">Reserved <b x-text="counts().reserved"></b></span>
        <span class="badge bg-orange-100 text-orange-800">Dirty <b x-text="counts().dirty"></b></span>
      </div>
    </div>
    <p x-show="!loading && !rooms.length" class="text-slate-500">No rooms yet. Add room types and rooms under Rooms.</p>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
      <template x-for="r in rooms" :key="r.id">
        <button class="rounded-xl border-2 p-3 text-left transition hover:shadow" :class="color(r)" @click="sel = r">
          <p class="text-lg font-bold" x-text="r.number"></p>
          <p class="text-xs capitalize" x-text="r.occupancy"></p>
          <p class="text-xs text-slate-500 capitalize" x-text="r.housekeeping_status.replace('_',' ')"></p>
          <p class="mt-1 truncate text-xs" x-text="r.reservation?.number || ''"></p>
        </button>
      </template>
    </div>
    <div x-show="sel" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="sel = null">
      <div class="card w-full max-w-sm space-y-3" x-show="sel">
        <h2 class="text-lg font-semibold">Room <span x-text="sel?.number"></span></h2>
        <p class="text-sm" x-text="sel?.reservation ? 'Reservation ' + sel.reservation.number + ' · ' + sel.reservation.check_in + ' → ' + sel.reservation.check_out : 'No reservation tonight'"></p>
        <div class="flex flex-wrap gap-2">
          <template x-for="s in ['clean','dirty','inspected','out_of_order']" :key="s"><button class="btn btn-secondary btn-sm capitalize" :class="sel?.housekeeping_status === s ? 'ring-2 ring-emerald-500' : ''" @click="setStatus(sel, s)" x-text="s.replace('_',' ')"></button></template>
        </div>
        <div class="flex justify-between"><a x-show="sel?.reservation" href="#/rooms/reservations" class="btn btn-secondary btn-sm">Open reservations</a><button class="btn btn-secondary btn-sm" @click="sel = null" x-text="$store.app.t('close')"></button></div>
      </div>
    </div>
  </div>
</template>

<template x-if="$store.app.route.page === 'rooms.reservations'">
  <div x-data="reservationsPage()">
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <h1 class="text-2xl font-bold" x-text="$store.app.t('reservations')"></h1>
      <input class="input w-56" :placeholder="$store.app.t('search')" x-model.debounce.300ms="q" @input="load()">
      <select class="input w-auto" x-model="status" @change="load()"><option value="">All</option><template x-for="s in ['pending','confirmed','checked_in','checked_out','cancelled','no_show']"><option :value="s" x-text="s.replace('_',' ')"></option></template></select>
      <div class="flex-1"></div>
      <button class="btn btn-primary" @click="newForm()" x-show="$store.app.can('rooms.create')">+ New reservation</button>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50"><tr><th class="th">#</th><th class="th" x-text="$store.app.t('check_in')"></th><th class="th" x-text="$store.app.t('check_out')"></th><th class="th" x-text="$store.app.t('room_types')"></th><th class="th" x-text="$store.app.t('status')"></th><th class="th" x-text="$store.app.t('total')"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <template x-for="r in rows" :key="r.id"><tr class="cursor-pointer hover:bg-slate-50" @click="open(r.id)">
            <td class="td font-medium" x-text="r.number"></td><td class="td" x-text="r.check_in"></td><td class="td" x-text="r.check_out"></td><td class="td" x-text="typeName(r.room_type_id)"></td>
            <td class="td"><span class="badge capitalize" :class="badge(r.status)" x-text="r.status.replace('_',' ')"></span></td><td class="td" x-text="money(r.total)"></td></tr></template>
          <tr x-show="!loading && !rows.length"><td class="td text-slate-400" colspan="6" x-text="$store.app.t('no_data')"></td></tr>
        </tbody>
      </table>
    </div>

    <!-- new reservation -->
    <div x-show="form" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
      <form class="card my-8 w-full max-w-2xl space-y-3" @submit.prevent="create()" x-show="form">
        <h2 class="text-lg font-semibold">New reservation</h2>
        <p x-show="formErr" x-text="formErr" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
        <template x-if="form"><div class="grid gap-3 sm:grid-cols-2">
          <label class="text-sm font-medium">Room type<select class="input mt-1" x-model="form.room_type_id" @change="check()" required><template x-for="t in types" :key="t.id"><option :value="t.id" x-text="t.name"></option></template></select></label>
          <label class="text-sm font-medium">Source<select class="input mt-1" x-model="form.source"><template x-for="s in ['walk_in','phone','website','agent','other']"><option :value="s" x-text="s.replace('_',' ')"></option></template></select></label>
          <label class="text-sm font-medium"><span x-text="$store.app.t('check_in')"></span><input type="date" class="input mt-1" x-model="form.check_in" @change="check()" required></label>
          <label class="text-sm font-medium"><span x-text="$store.app.t('check_out')"></span><input type="date" class="input mt-1" x-model="form.check_out" @change="check()" required></label>
          <label class="text-sm font-medium">Adults<input type="number" min="1" class="input mt-1" x-model.number="form.adults"></label>
          <label class="text-sm font-medium">Children<input type="number" min="0" class="input mt-1" x-model.number="form.children"></label>
          <label class="text-sm font-medium"><span x-text="$store.app.t('guest') + ' *'"></span><input class="input mt-1" x-model="form.guest.name" required></label>
          <label class="text-sm font-medium"><span x-text="$store.app.t('phone')"></span><input class="input mt-1" x-model="form.guest.phone"></label>
          <label class="text-sm font-medium">Email<input type="email" class="input mt-1" x-model="form.guest.email"></label>
          <label class="text-sm font-medium">Nationality<input class="input mt-1" x-model="form.guest.nationality"></label>
          <label class="text-sm font-medium sm:col-span-2">Notes<textarea class="input mt-1" rows="2" x-model="form.notes"></textarea></label>
        </div></template>
        <div x-show="quote" class="rounded-lg bg-emerald-50 p-3 text-sm"><b x-text="quote?.free"></b> <span x-text="$store.app.t('free')"></span> · <span x-text="quote?.nights"></span> <span x-text="$store.app.t('nights')"></span> · <b x-text="money(quote?.total)"></b> <span class="text-slate-500" x-show="quote?.tax_total">(incl. taxes <span x-text="money(quote?.tax_total)"></span>)</span></div>
        <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="form = null" x-text="$store.app.t('cancel')"></button><button class="btn btn-primary" :disabled="busy || (quote && quote.free < 1)" x-text="$store.app.t('save')"></button></div>
      </form>
    </div>

    <!-- detail -->
    <div x-show="view" x-cloak class="fixed inset-0 z-40 flex justify-end bg-black/40" @click.self="view = null">
      <div class="h-full w-full max-w-xl space-y-4 overflow-y-auto bg-white p-5 shadow-xl" x-show="view">
        <template x-if="view">
          <div class="space-y-4">
            <div class="flex items-start justify-between"><div><h2 class="text-xl font-bold" x-text="view.data.number"></h2><p class="text-sm text-slate-500" x-text="(view.guest?.name || '') + ' · ' + view.data.check_in + ' → ' + view.data.check_out"></p></div><button class="btn btn-secondary btn-sm" @click="view = null" x-text="$store.app.t('close')"></button></div>
            <span class="badge capitalize" :class="badge(view.data.status)" x-text="view.data.status.replace('_',' ')"></span>
            <p class="text-sm text-slate-600">Stay total <b x-text="money(view.data.total)"></b> <span class="text-slate-400">(room charges post to the folio nightly at night audit)</span></p>
            <div class="card space-y-2" x-show="['pending','confirmed'].includes(view.data.status)">
              <label class="text-sm font-medium"><span x-text="$store.app.t('room')"></span>
                <select class="input mt-1" :value="view.data.room_id" @change="act('assign', { room_id: $event.target.value })"><option value="">—</option><template x-for="r in view.rooms" :key="r.id"><option :value="r.id" x-text="r.number + ' (' + r.housekeeping_status + ')'"></option></template></select></label>
              <label class="text-sm font-medium" x-show="$store.app.setting('rooms.require_id_on_checkin', true) && !view.guest?.id_number">Guest ID number<input class="input mt-1" x-model="view.idNumber"></label>
              <div class="flex flex-wrap gap-2"><button class="btn btn-primary btn-sm" @click="checkIn()" x-show="$store.app.can('rooms.check_in')" x-text="$store.app.t('check_in')"></button>
                <button class="btn btn-secondary btn-sm" @click="act('cancel', { reason: prompt('Reason?') || '' })">Cancel booking</button><button class="btn btn-secondary btn-sm" @click="act('no-show')">No-show</button></div>
            </div>
            <div x-show="view.data.status === 'checked_in'"><button class="btn btn-primary" @click="act('check-out')" x-show="$store.app.can('rooms.check_out')" x-text="$store.app.t('check_out')"></button></div>
            <template x-if="view.folio">
              <div class="card space-y-3">
                <div class="flex items-center justify-between"><h3 class="font-semibold">Folio <span class="text-slate-400" x-text="view.folio.number"></span></h3><p class="text-sm"><span x-text="$store.app.t('balance')"></span>: <b x-text="money(view.folio.balance)"></b></p></div>
                <table class="min-w-full text-sm"><tbody class="divide-y divide-slate-100">
                  <template x-for="e in view.folio.entries" :key="e.id"><tr :class="e.voided_at ? 'text-slate-300 line-through' : ''">
                    <td class="py-1" x-text="e.description"></td><td class="py-1 text-right" x-text="money(e.amount)"></td>
                    <td class="py-1 pl-2 text-right"><button x-show="!e.voided_at && view.folio.status === 'open' && $store.app.can('rooms.folio')" class="text-xs text-red-600" @click="voidEntry(e)">void</button></td></tr></template>
                </tbody></table>
                <div x-show="view.folio.status === 'open' && $store.app.can('rooms.folio')" class="space-y-2">
                  <div class="flex gap-2"><input class="input" placeholder="Charge description" x-model="view.charge.description"><input class="input w-28" type="number" placeholder="RWF" x-model="view.charge.amount"><button class="btn btn-secondary" @click="charge()" :disabled="!view.charge.description || !view.charge.amount">+</button></div>
                  <div class="flex gap-2"><select class="input w-32" x-model="view.pay.method_type"><template x-for="m in methods.filter(m => m.type !== 'room_charge')" :key="m.key"><option :value="m.type" x-text="m.name"></option></template></select>
                    <input class="input w-28" type="number" placeholder="RWF" x-model="view.pay.amount"><input class="input" x-show="view.pay.method_type === 'momo'" placeholder="2507…" x-model="view.pay.payer_phone"><button class="btn btn-primary" @click="pay()" :disabled="!view.pay.amount" x-text="$store.app.t('pay')"></button></div>
                </div>
              </div>
            </template>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
@endverbatim
