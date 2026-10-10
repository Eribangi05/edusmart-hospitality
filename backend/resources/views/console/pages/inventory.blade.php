@verbatim
<template x-if="$store.app.route.page === 'inventory.stock'">
  <div x-data="stockPage()">
    <div class="mb-4 flex flex-wrap items-center gap-3"><h1 class="text-2xl font-bold" x-text="$store.app.t('stock')"></h1>
      <label class="flex items-center gap-1 text-sm"><input type="checkbox" x-model="lowOnly" @change="load()"> Low only</label><div class="flex-1"></div>
      <button class="btn btn-secondary" x-show="$store.app.can('inventory.stocktake') && !count" @click="startCount()">Stocktake</button>
      <button class="btn btn-primary" x-show="count" @click="saveCount()">Save counts</button><button class="btn btn-secondary" x-show="count" @click="count = null" x-text="$store.app.t('cancel')"></button></div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-full divide-y divide-slate-200">
      <thead class="bg-slate-50"><tr><th class="th" x-text="$store.app.t('name')"></th><th class="th">Unit</th><th class="th">On hand</th><th class="th">Reorder at</th><th class="th" x-show="count">Counted</th><th class="th"></th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="r in rows" :key="r.id"><tr :class="r.low ? 'bg-red-50' : ''">
        <td class="td font-medium" x-text="r.name"></td><td class="td" x-text="r.unit"></td><td class="td" x-text="num(r.quantity)"></td><td class="td" x-text="num(r.reorder_level)"></td>
        <td class="td" x-show="count"><input class="input w-24" type="number" step="any" x-model="counts[r.id]"></td>
        <td class="td text-right"><button x-show="$store.app.can('inventory.adjust')" class="btn btn-secondary btn-sm" @click="openAdjust(r)">Adjust</button></td></tr></template>
      <tr x-show="!loading && !rows.length"><td class="td text-slate-400" colspan="6" x-text="$store.app.t('no_data')"></td></tr></tbody></table></div>
    <div x-show="adj" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"><template x-if="adj"><form class="card w-full max-w-sm space-y-3" @submit.prevent="saveAdjust()"><h2 class="font-semibold" x-text="adj.item.name"></h2>
      <label class="text-sm font-medium">Type<select class="input mt-1" x-model="adj.type"><option value="purchase">Received / purchase</option><option value="waste">Waste / loss</option><option value="adjustment">Adjustment (+/−)</option><option value="return">Return</option></select></label>
      <label class="text-sm font-medium">Quantity<input class="input mt-1" type="number" step="any" x-model="adj.quantity" required></label><label class="text-sm font-medium">Note<input class="input mt-1" x-model="adj.note"></label>
      <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="adj = null" x-text="$store.app.t('cancel')"></button><button class="btn btn-primary" x-text="$store.app.t('save')"></button></div></form></template></div>
  </div>
</template>

<template x-if="$store.app.route.page === 'inventory.po'">
  <div x-data="poPage()">
    <div class="mb-4 flex items-center gap-3"><h1 class="text-2xl font-bold" x-text="$store.app.t('purchase_orders')"></h1><div class="flex-1"></div><button class="btn btn-primary" @click="newForm()" x-show="$store.app.can('inventory.create')">+ New</button></div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th class="th">#</th><th class="th" x-text="$store.app.t('status')"></th><th class="th" x-text="$store.app.t('total')"></th><th class="th"></th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="p in rows" :key="p.id"><tr><td class="td font-medium" x-text="p.number"></td><td class="td capitalize" x-text="p.status"></td><td class="td" x-text="money(p.total)"></td>
        <td class="td text-right"><button class="btn btn-secondary btn-sm" x-show="['ordered','draft','partial'].includes(p.status) && $store.app.can('inventory.receive')" @click="startReceive(p)">Receive</button></td></tr></template>
      <tr x-show="!loading && !rows.length"><td class="td text-slate-400" colspan="4" x-text="$store.app.t('no_data')"></td></tr></tbody></table></div>
    <div x-show="form" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4"><form class="card my-8 w-full max-w-2xl space-y-3" @submit.prevent="save()" x-show="form"><h2 class="font-semibold">New purchase order</h2>
      <template x-if="form"><div class="space-y-3"><div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-medium">Supplier<select class="input mt-1" x-model="form.supplier_id"><option value="">—</option><template x-for="s in suppliers" :key="s.id"><option :value="s.id" x-text="s.name"></option></template></select></label><label class="text-sm font-medium">Expected<input type="date" class="input mt-1" x-model="form.expected_on"></label></div>
        <template x-for="(l, i) in form.lines" :key="i"><div class="grid grid-cols-[1fr_6rem_7rem] gap-2"><select class="input" x-model="l.inventory_item_id"><template x-for="it in items" :key="it.id"><option :value="it.id" x-text="it.name"></option></template></select><input class="input" type="number" step="any" min="0" x-model="l.quantity"><input class="input" type="number" min="0" x-model="l.unit_cost"></div></template>
        <button type="button" class="btn btn-secondary btn-sm" @click="addLine()">+ line</button></div></template>
      <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="form = null" x-text="$store.app.t('cancel')"></button><button class="btn btn-primary" x-text="$store.app.t('save')"></button></div></form></div>
    <div x-show="recv" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"><form class="card w-full max-w-md space-y-3" @submit.prevent="receive()" x-show="recv"><h2 class="font-semibold">Receive goods · <span x-text="recv?.po.number"></span></h2>
      <template x-if="recv"><div class="space-y-2"><template x-for="l in recv.po.lines" :key="l.inventory_item_id"><div class="flex items-center justify-between gap-2 text-sm"><span x-text="itemName(l.inventory_item_id)"></span><input class="input w-28" type="number" step="any" min="0" x-model="recv.qty[l.inventory_item_id]"></div></template></div></template>
      <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="recv = null" x-text="$store.app.t('cancel')"></button><button class="btn btn-primary" x-text="$store.app.t('confirm')"></button></div></form></div>
  </div>
</template>
@endverbatim
