@verbatim
<template x-if="$store.app.route.page === 'pos.orders'">
  <div x-data="ordersPage()">
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <h1 class="text-2xl font-bold" x-text="$store.app.t('orders')"></h1>
      <select class="input w-auto" x-model="status" @change="load()"><option value="">All</option><template x-for="s in ['open','sent','paid','void']"><option :value="s" x-text="s"></option></template></select>
      <button class="btn btn-secondary" @click="load()" x-text="$store.app.t('refresh')"></button>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-full divide-y divide-slate-200">
      <thead class="bg-slate-50"><tr><th class="th">#</th><th class="th" x-text="$store.app.t('date')"></th><th class="th">Type</th><th class="th" x-text="$store.app.t('status')"></th><th class="th" x-text="$store.app.t('total')"></th><th class="th" x-text="$store.app.t('paid')"></th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="o in rows" :key="o.id"><tr class="cursor-pointer hover:bg-slate-50" @click="open(o.id)">
        <td class="td font-medium" x-text="o.number"></td><td class="td" x-text="(o.created_at || '').slice(0,16).replace('T',' ')"></td><td class="td" x-text="o.type.replace('_',' ')"></td>
        <td class="td"><span class="badge" :class="badge(o.status)" x-text="o.status"></span></td><td class="td" x-text="money(o.total)"></td><td class="td" x-text="money(o.paid)"></td></tr></template>
      <tr x-show="!loading && !rows.length"><td class="td text-slate-400" colspan="6" x-text="$store.app.t('no_data')"></td></tr></tbody></table></div>
    <div x-show="view" x-cloak class="fixed inset-0 z-40 flex justify-end bg-black/40" @click.self="view = null">
      <div class="h-full w-full max-w-lg space-y-4 overflow-y-auto bg-white p-5 shadow-xl" x-show="view"><template x-if="view"><div class="space-y-4">
        <div class="flex items-start justify-between"><div><h2 class="text-xl font-bold" x-text="view.number"></h2><span class="badge" :class="badge(view.status)" x-text="view.status"></span></div><button class="btn btn-secondary btn-sm" @click="view = null" x-text="$store.app.t('close')"></button></div>
        <table class="min-w-full text-sm"><tbody class="divide-y divide-slate-100"><template x-for="i in view.items" :key="i.id"><tr :class="i.voided_at ? 'text-slate-300 line-through' : ''">
          <td class="py-1"><span x-text="Number(i.quantity) + ' × ' + i.name"></span></td><td class="py-1 text-right" x-text="money(i.line_total)"></td>
          <td class="py-1 pl-2 text-right"><button x-show="!i.voided_at && !['paid','void'].includes(view.status)" class="text-xs text-red-600" @click="voidItem(i)">void</button></td></tr></template></tbody></table>
        <dl class="space-y-1 text-sm"><div class="flex justify-between"><dt>Subtotal</dt><dd x-text="money(view.subtotal)"></dd></div><div class="flex justify-between" x-show="view.discount"><dt>Discount</dt><dd x-text="'-' + money(view.discount)"></dd></div><div class="flex justify-between"><dt>Tax</dt><dd x-text="money(view.tax)"></dd></div><div class="flex justify-between" x-show="view.service"><dt>Service</dt><dd x-text="money(view.service)"></dd></div><div class="flex justify-between border-t pt-1 font-bold"><dt x-text="$store.app.t('total')"></dt><dd x-text="money(view.total)"></dd></div><div class="flex justify-between"><dt x-text="$store.app.t('balance')"></dt><dd x-text="money(view.balance)"></dd></div></dl>
        <template x-for="p in view.payments" :key="p.id"><div class="flex items-center justify-between text-sm"><span class="capitalize" x-text="p.method_type + ' · ' + p.status"></span><span><span x-text="money(p.amount)"></span> <button x-show="p.status === 'pending'" class="btn btn-secondary btn-sm" @click="refreshPayment(p)" x-text="$store.app.t('refresh')"></button></span></div></template>
        <div x-show="!['paid','void'].includes(view.status) && $store.app.can('pos.create')" class="card space-y-2">
          <div class="flex gap-2"><select class="input w-36" x-model="view.pay.method_type"><template x-for="m in methods.filter(m => m.type !== 'room_charge')" :key="m.key"><option :value="m.type" x-text="m.name"></option></template></select><input class="input" type="number" x-model="view.pay.amount"><button class="btn btn-primary" @click="pay()" x-text="$store.app.t('pay')"></button></div>
          <input class="input" x-show="view.pay.method_type === 'momo'" placeholder="MoMo number 2507…" x-model="view.pay.payer_phone">
          <div class="flex gap-2"><input class="input" placeholder="Void reason" x-model="view.reason"><input class="input w-28" placeholder="Mgr PIN" type="password" x-model="view.pin"><button class="btn btn-danger" @click="voidOrder()">Void</button></div>
        </div>
      </div></template></div>
    </div>
  </div>
</template>

<template x-if="$store.app.route.page === 'pos.new'">
  <div x-data="newOrderPage()" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
    <section>
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <h1 class="mr-2 text-2xl font-bold" x-text="$store.app.t('new_order')"></h1>
        <input class="input w-48" :placeholder="$store.app.t('search')" x-model="q">
        <button class="btn btn-secondary btn-sm" :class="!cat ? 'ring-2 ring-emerald-500' : ''" @click="cat = ''">All</button>
        <template x-for="c in cats" :key="c.id"><button class="btn btn-secondary btn-sm" :class="cat === c.id ? 'ring-2 ring-emerald-500' : ''" @click="cat = c.id" x-text="c.name"></button></template>
      </div>
      <div x-show="!shift" class="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">No open cash shift. <button class="underline" @click="openShift()">Open shift</button> to track cash-up.</div>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
        <template x-for="i in shown" :key="i.id"><button class="card text-left transition hover:shadow-md" @click="add(i)"><p class="font-medium" x-text="i.name"></p><p class="text-sm text-emerald-700" x-text="money(i.price)"></p><p class="text-xs capitalize text-slate-400" x-text="i.kind"></p></button></template>
      </div>
      <p x-show="!shown.length" class="text-slate-500">No items. Add menu items under <a class="underline" href="#/menu">Menu &amp; products</a>.</p>
    </section>
    <aside class="card h-fit space-y-3 lg:sticky lg:top-20">
      <template x-if="!order">
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-2"><select class="input" x-model="type"><option value="dine_in">Dine in</option><option value="takeaway">Takeaway</option><option value="room_service">Room service</option><option value="retail">Retail</option></select>
            <select class="input" x-model="tableId"><option value="">No table</option><template x-for="t in tables" :key="t.id"><option :value="t.id" x-text="t.name"></option></template></select></div>
          <input class="input" placeholder="Customer (optional)" x-model="customer">
        </div>
      </template>
      <template x-if="order"><div class="text-sm"><b x-text="order.number"></b> · <span class="capitalize" x-text="order.status"></span></div></template>
      <ul class="divide-y divide-slate-100 text-sm">
        <template x-if="order"><template x-for="i in order.items" :key="i.id"><li class="flex justify-between py-1" :class="i.voided_at ? 'text-slate-300 line-through' : ''"><span x-text="Number(i.quantity) + ' × ' + i.name"></span><span x-text="money(i.line_total)"></span></li></template></template>
        <template x-for="l in cart" :key="l.catalog_item_id"><li class="flex items-center justify-between gap-2 py-1"><span x-text="l.name"></span><span class="flex items-center gap-1"><button class="btn btn-secondary btn-sm" @click="dec(l)">−</button><b x-text="l.quantity"></b><button class="btn btn-secondary btn-sm" @click="l.quantity++">+</button></span></li></template>
      </ul>
      <p class="text-sm text-slate-500" x-show="cart.length">Estimate <b x-text="money(estimate)"></b> (taxes added by the server)</p>
      <template x-if="order"><dl class="space-y-0.5 text-sm"><div class="flex justify-between"><dt>Tax</dt><dd x-text="money(order.tax)"></dd></div><div class="flex justify-between font-bold"><dt x-text="$store.app.t('total')"></dt><dd x-text="money(order.total)"></dd></div><div class="flex justify-between"><dt x-text="$store.app.t('balance')"></dt><dd x-text="money(order.balance)"></dd></div></dl></template>
      <div class="flex gap-2" x-show="cart.length"><button class="btn btn-secondary flex-1" :disabled="busy" @click="submit(false)">Save</button><button class="btn btn-primary flex-1" :disabled="busy" @click="submit(true)">Send</button></div>
      <template x-if="order && order.status !== 'paid' && order.status !== 'void' && !cart.length">
        <div class="space-y-2 border-t pt-3">
          <div class="flex gap-2"><select class="input" x-model="pay.method_type"><template x-for="m in methods" :key="m.key"><option :value="m.type" x-text="m.name"></option></template></select><button class="btn btn-primary" :disabled="busy || !order.balance" @click="settle()" x-text="$store.app.t('pay') + ' ' + money(order.balance)"></button></div>
          <input class="input" x-show="pay.method_type === 'momo'" placeholder="MoMo number 2507…" x-model="pay.payer_phone">
          <button class="btn btn-secondary w-full btn-sm" x-show="order.payments.some(p => p.status === 'pending')" @click="refreshPay()">Check mobile-money status</button>
        </div>
      </template>
      <button class="btn btn-secondary w-full" x-show="order" @click="reset()">New order</button>
    </aside>
  </div>
</template>

<template x-if="$store.app.route.page === 'pos.kitchen'">
  <div x-data="kitchenPage()" x-init="$watch('station', () => load())">
    <div class="mb-4 flex items-center gap-3"><h1 class="text-2xl font-bold" x-text="$store.app.t('kitchen')"></h1>
      <select class="input w-auto" x-model="station"><option value="kitchen">Kitchen</option><option value="bar">Bar</option></select></div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <template x-for="tk in tickets" :key="tk.order?.id"><div class="card space-y-2"><div class="flex justify-between text-sm"><b x-text="tk.order?.number"></b><span class="text-slate-500" x-text="age(tk.items[0]?.updated_at)"></span></div>
        <template x-for="i in tk.items" :key="i.id"><div class="flex items-center justify-between gap-2 text-sm"><span><b x-text="Number(i.quantity) + '×'"></b> <span x-text="i.name"></span><span class="block text-xs text-slate-400" x-text="i.notes || ''"></span></span>
          <span class="flex gap-1"><button x-show="i.status === 'sent'" class="btn btn-secondary btn-sm" @click="mark(i, 'preparing')">Start</button><button x-show="i.status !== 'ready'" class="btn btn-primary btn-sm" @click="mark(i, 'ready')">Ready</button><button x-show="i.status === 'ready'" class="btn btn-secondary btn-sm" @click="mark(i, 'served')">Served</button></span></div></template></div></template>
    </div>
    <p x-show="!tickets.length" class="text-slate-500">All clear. New tickets appear here automatically.</p>
  </div>
</template>

<template x-if="$store.app.route.page === 'pos.shifts'">
  <div x-data="shiftsPage()" class="max-w-2xl space-y-4">
    <h1 class="text-2xl font-bold" x-text="$store.app.t('shifts')"></h1>
    <div class="card space-y-3">
      <template x-if="!shift"><div class="flex gap-2"><input class="input" type="number" placeholder="Opening float (RWF)" x-model="float"><button class="btn btn-primary" @click="open()">Open shift</button></div></template>
      <template x-if="shift"><div class="space-y-2"><p class="text-sm">Shift open since <b x-text="(shift.opened_at || '').slice(0,16).replace('T',' ')"></b> · float <b x-text="money(shift.opening_float)"></b></p>
        <div class="flex gap-2"><input class="input" type="number" placeholder="Counted cash (RWF)" x-model="counted"><button class="btn btn-danger" @click="close()" :disabled="counted === ''">Close shift</button></div></div></template>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" x-show="rows.length"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th class="th">Opened</th><th class="th">Expected</th><th class="th">Counted</th><th class="th">Variance</th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="s in rows" :key="s.id"><tr><td class="td" x-text="(s.opened_at || '').slice(0,16).replace('T',' ')"></td><td class="td" x-text="money(s.expected_cash)"></td><td class="td" x-text="s.counted_cash === null ? '' : money(s.counted_cash)"></td><td class="td" :class="s.variance < 0 ? 'text-red-600' : ''" x-text="s.variance === null ? '' : money(s.variance)"></td></tr></template></tbody></table></div>
  </div>
</template>
@endverbatim
