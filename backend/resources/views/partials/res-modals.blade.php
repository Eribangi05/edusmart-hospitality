@verbatim
  <!-- reservation drawer -->
  <div x-data="resDrawer()" @open-res.window="open($event.detail.id)">
    <div x-show="v" class="fixed inset-0 z-40 flex justify-end bg-black/40" @click.self="close()">
      <div class="h-full w-full max-w-xl space-y-4 overflow-y-auto bg-white p-5 shadow-xl" x-show="v"><template x-if="v"><div class="space-y-4">
        <div class="flex items-start justify-between"><div><h2 class="text-xl font-bold" x-text="v.data.number"></h2><p class="text-sm text-slate-500" x-text="(v.guest?.name || '') + ' · ' + v.data.check_in + ' → ' + v.data.check_out"></p></div><button class="btn btn-secondary btn-sm" @click="close()">✕</button></div>
        <span class="badge capitalize" :class="badge(v.data.status)" x-text="v.data.status.replace('_',' ')"></span>
        <p x-show="msg" x-text="msg" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
        <div class="card space-y-2" x-show="['pending','confirmed'].includes(v.data.status)">
          <label class="text-sm font-medium">Room<select class="input mt-1" :value="v.data.room_id" @change="act('assign', { room_id: $event.target.value })"><option value="">—</option><template x-for="r in v.rooms" :key="r.id"><option :value="r.id" x-text="r.number + ' (' + r.housekeeping_status + ')'"></option></template></select></label>
          <label class="text-sm font-medium" x-show="!v.guest?.id_number">Guest ID / passport number<input class="input mt-1" x-model="v.idNumber"></label>
          <div class="flex flex-wrap gap-2"><button class="btn btn-primary" :disabled="busy" @click="checkIn()">Check in</button><button class="btn btn-secondary" @click="act('cancel', { reason: prompt('Reason?') || '' })">Cancel</button><button class="btn btn-secondary" @click="act('no-show')">No-show</button></div></div>
        <div x-show="v.data.status === 'checked_in'" class="flex flex-wrap gap-2"><button class="btn btn-primary" :disabled="busy" @click="checkOut()">Check out</button><button class="btn btn-secondary" x-show="v.folio && v.folio.balance > 0 && can('rooms.folio')" @click="checkOut(true)">Check out with balance</button></div>
        <template x-if="v.folio"><div class="card space-y-3"><div class="flex items-center justify-between"><h3 class="font-semibold">Guest bill <span class="text-slate-400" x-text="v.folio.number"></span></h3><div class="text-right text-sm"><p>Balance <b x-text="money(v.folio.balance)"></b></p><button class="text-xs text-emerald-700 underline" @click="printInvoice()">Print invoice</button></div></div>
          <table class="min-w-full text-sm"><tbody class="divide-y divide-slate-100"><template x-for="e in v.folio.entries" :key="e.id"><tr :class="e.voided_at ? 'text-slate-300 line-through' : ''"><td class="py-1" x-text="e.description"></td><td class="py-1 text-right" x-text="money(e.amount)"></td><td class="pl-2 text-right"><button x-show="!e.voided_at && v.folio.status === 'open' && can('rooms.folio')" class="text-xs text-red-600" @click="voidEntry(e)">void</button></td></tr></template></tbody></table>
          <div x-show="v.folio.status === 'open' && can('rooms.folio')" class="space-y-2"><div class="flex gap-2"><input class="input" placeholder="Extra charge (laundry…)" x-model="v.charge.description"><input class="input w-28" type="number" placeholder="RWF" x-model="v.charge.amount"><button class="btn btn-secondary" @click="addCharge()" :disabled="!v.charge.description || !v.charge.amount">+</button></div>
            <div class="flex gap-2"><select class="input w-36" x-model="v.pay.method_type"><template x-for="m in methods" :key="m.key"><option :value="m.type" x-text="m.name"></option></template></select><input class="input w-28" type="number" x-model="v.pay.amount" placeholder="RWF"><input class="input" x-show="v.pay.method_type === 'momo'" placeholder="2507…" x-model="v.pay.payer_phone"><button class="btn btn-primary" :disabled="!v.pay.amount" @click="pay()">Take payment</button></div></div></div></template>
      </div></template></div></div>
  </div>

  <!-- new reservation -->
  <div x-data="newRes()" @new-res.window="start($event.detail || {})" @created.window="$dispatch('open-res', $event.detail); $dispatch('changed')">
    <div x-show="f" class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4"><form class="card my-8 w-full max-w-xl space-y-3" @submit.prevent="create()" x-show="f"><h2 class="text-lg font-bold">New reservation</h2>
      <p x-show="err" x-text="err" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
      <template x-if="f"><div class="grid gap-3 sm:grid-cols-2">
        <label class="text-sm font-medium">Room type<select class="input mt-1" x-model="f.room_type_id" @change="f.room_id = ''; check()"><template x-for="t in types" :key="t.id"><option :value="t.id" x-text="t.name"></option></template></select></label>
        <label class="text-sm font-medium">Source<select class="input mt-1" x-model="f.source"><template x-for="s in ['walk_in','phone','website','agent','other']"><option :value="s" x-text="s.replace('_',' ')"></option></template></select></label>
        <label class="text-sm font-medium">Check-in<input type="date" class="input mt-1" x-model="f.check_in" @change="check()" required></label><label class="text-sm font-medium">Check-out<input type="date" class="input mt-1" x-model="f.check_out" @change="check()" required></label>
        <label class="text-sm font-medium">Adults<input type="number" min="1" class="input mt-1" x-model.number="f.adults"></label><label class="text-sm font-medium">Children<input type="number" min="0" class="input mt-1" x-model.number="f.children"></label>
        <template x-if="!f.guest_id"><label class="text-sm font-medium sm:col-span-2">Guest name<input class="input mt-1" x-model="f.guest.name" required></label></template>
        <template x-if="!f.guest_id"><label class="text-sm font-medium">Phone<input class="input mt-1" x-model="f.guest.phone"></label></template><template x-if="!f.guest_id"><label class="text-sm font-medium">Nationality<input class="input mt-1" x-model="f.guest.nationality"></label></template>
        <p x-show="f.guest_id" class="text-sm text-emerald-700 sm:col-span-2">Existing guest selected: <b x-text="f.guest.name"></b></p>
      </div></template>
      <div x-show="quote" class="rounded-lg bg-emerald-50 p-3 text-sm"><b x-text="quote?.free"></b> free · <span x-text="quote?.nights"></span> nights · <b x-text="money(quote?.total)"></b></div>
      <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="f = null">Cancel</button><button class="btn btn-primary" :disabled="busy || (quote && quote.free < 1)">Create</button></div></form></div>
  </div>

@endverbatim
