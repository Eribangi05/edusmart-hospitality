<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Sales terminal · Edusmart Hospitality</title>
  @vite(['resources/css/app.css', 'resources/js/terminal.js'])
</head>
<body class="h-full overflow-hidden bg-slate-100 text-slate-900 antialiased select-none">
@verbatim
<div x-data="terminal()" x-cloak class="flex h-full flex-col">
  <!-- top bar -->
  <header class="flex items-center gap-2 border-b border-slate-200 bg-white px-3 py-2">
    <a href="/workspaces" class="btn btn-secondary btn-sm" title="Switch workspace">☰</a>
    <p class="font-bold text-emerald-700">Sales terminal</p>
    <div class="flex gap-1 rounded-lg bg-slate-100 p-1 text-xs font-medium">
      <template x-for="t in types" :key="t.k"><button class="rounded-md px-2.5 py-1" :class="type === t.k ? 'bg-white shadow text-emerald-700' : 'text-slate-600'" @click="setType(t.k)" x-text="t.l"></button></template>
    </div>
    <div class="flex-1"></div>
    <button class="btn btn-sm" :class="shift ? 'btn-secondary' : 'bg-amber-500 text-white'" @click="shiftModal = true"><span x-text="shift ? 'Shift open · ' + money(shift.opening_float) : 'No open shift'"></span></button>
    <span class="hidden text-sm tabular-nums text-slate-500 sm:inline" x-text="clock"></span>
    <span class="hidden text-sm text-slate-600 md:inline" x-text="user"></span>
    <button class="btn btn-secondary btn-sm" @click="theme()" title="Dark / light">◐</button>
  </header>
  <p x-show="error" x-text="error" class="bg-red-600 px-3 py-1.5 text-sm text-white" role="alert" @click="error = ''"></p>

  <div class="grid min-h-0 flex-1 gap-0 lg:grid-cols-[17rem_1fr_24rem]">
    <!-- tables / open tickets -->
    <aside class="hidden min-h-0 flex-col overflow-y-auto border-r border-slate-200 bg-white p-3 lg:flex" :class="pane === 'tables' ? '!flex fixed inset-0 z-20 lg:static' : ''">
      <div class="mb-2 flex items-center justify-between"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tables</p><button class="btn btn-secondary btn-sm lg:hidden" @click="pane = 'menu'">Close</button></div>
      <button class="mb-3 w-full rounded-xl border-2 border-dashed border-emerald-400 py-3 text-sm font-semibold text-emerald-700" @click="newTicket()">+ New ticket (no table)</button>
      <template x-for="[sec, list] in sections()" :key="sec">
        <div class="mb-3"><p class="mb-1 text-xs font-medium text-slate-500" x-text="sec"></p>
          <div class="grid grid-cols-3 gap-2 lg:grid-cols-2">
            <template x-for="t in list" :key="t.id"><button class="touch-tile rounded-xl border-2 p-2 text-left text-sm" :class="tableClass(t)" @click="pickTable(t)">
              <b x-text="t.name"></b><span class="block text-xs" x-text="tableInfo(t)"></span></button></template>
          </div></div>
      </template>
      <p x-show="!tables.length" class="text-sm text-slate-500">No tables set up. Use "New ticket" or add tables in the manager console.</p>
      <p class="mb-1 mt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Other open tickets</p>
      <template x-for="o in looseOrders()" :key="o.id"><button class="mb-2 w-full rounded-lg border border-slate-200 p-2 text-left text-sm" @click="openOrder(o.id)"><b x-text="o.number"></b> <span class="text-slate-500" x-text="'· ' + (o.customer_name || o.type.replace('_',' '))"></span><span class="block text-xs" x-text="money(o.total)"></span></button></template>
    </aside>

    <!-- menu -->
    <section class="flex min-h-0 flex-col p-3">
      <div class="mb-2 flex items-center gap-2">
        <button class="btn btn-secondary lg:hidden" @click="pane = 'tables'">Tables</button>
        <input class="input" placeholder="Search item or scan barcode…" x-model="q" @keydown.enter="scanOrSearch()" x-ref="search">
        <button class="btn btn-secondary" @click="q = ''">✕</button>
      </div>
      <div class="mb-2 flex gap-2 overflow-x-auto pb-1">
        <button class="btn whitespace-nowrap" :class="!cat ? 'btn-primary' : 'btn-secondary'" @click="cat = ''">All</button>
        <template x-for="c in cats" :key="c.id"><button class="btn whitespace-nowrap" :class="cat === c.id ? 'btn-primary' : 'btn-secondary'" @click="cat = c.id" x-text="c.name"></button></template>
      </div>
      <div class="grid flex-1 content-start gap-2 overflow-y-auto pb-24 sm:grid-cols-3 xl:grid-cols-4" style="grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr))">
        <template x-for="i in shown()" :key="i.id"><button class="touch-tile rounded-xl bg-white p-3 text-left shadow-sm ring-1 ring-slate-200 transition active:scale-95 hover:ring-emerald-400" @click="add(i)">
          <span class="block text-sm font-semibold leading-tight" x-text="i.name"></span><span class="mt-1 block text-sm text-emerald-700" x-text="money(i.price)"></span>
          <span class="mt-1 block text-[10px] uppercase tracking-wide text-slate-400" x-text="i.station !== 'none' ? i.station : ''"></span></button></template>
        <p x-show="!shown().length" class="col-span-full py-10 text-center text-slate-500">No items. Add menu items in the manager console.</p>
      </div>
    </section>

    <!-- ticket -->
    <aside class="flex min-h-0 flex-col border-l border-slate-200 bg-white" :class="pane === 'ticket' ? 'fixed inset-0 z-20 lg:static' : 'hidden lg:flex'">
      <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
        <div><p class="font-bold" x-text="order ? order.number : 'New ticket'"></p><p class="text-xs text-slate-500" x-text="ticketSubtitle()"></p></div>
        <button class="btn btn-secondary btn-sm lg:hidden" @click="pane = 'menu'">Back</button>
      </div>
      <div class="min-h-0 flex-1 overflow-y-auto px-4 py-2">
        <template x-if="order"><div>
          <template x-for="l in order.items" :key="l.id"><div class="flex items-center gap-2 border-b border-slate-100 py-1.5 text-sm" :class="l.voided_at ? 'text-slate-300 line-through' : ''">
            <span class="w-9 text-right font-semibold" x-text="Number(l.quantity) + '×'"></span><span class="flex-1"><span x-text="l.name"></span><span class="block text-[11px] text-slate-400" x-text="l.status + (l.notes ? ' · ' + l.notes : '')"></span></span>
            <span x-text="money(l.line_total)"></span><button x-show="!l.voided_at && order.status !== 'paid'" class="text-red-500" @click="askVoid(l)" title="Void">✕</button></div></template>
        </div></template>
        <template x-for="(l, idx) in draft" :key="idx"><div class="flex items-center gap-2 border-b border-emerald-100 bg-emerald-50 px-1 py-1.5 text-sm">
          <div class="flex items-center gap-1"><button class="btn btn-secondary btn-sm !px-2" @click="dec(idx)">−</button><b class="w-5 text-center" x-text="l.quantity"></b><button class="btn btn-secondary btn-sm !px-2" @click="l.quantity++">+</button></div>
          <span class="flex-1"><span x-text="l.name"></span><input class="mt-0.5 w-full rounded border-0 bg-transparent p-0 text-[11px] text-slate-500 focus:ring-0" placeholder="note (no onion…)" x-model="l.notes"></span><span x-text="money(l.price * l.quantity)"></span></div></template>
        <p x-show="!draft.length && (!order || !order.items.length)" class="py-10 text-center text-sm text-slate-400">Tap items to add them</p>
      </div>
      <div class="space-y-2 border-t border-slate-200 p-4 text-sm">
        <div class="flex gap-2" x-show="order && order.status !== 'paid' && order.status !== 'void'">
          <input class="input !py-1.5" placeholder="Promo code" x-model="code" @keydown.enter="applyCode()"><button class="btn btn-secondary btn-sm" @click="applyCode()">Apply</button>
          <button class="btn btn-secondary btn-sm" @click="discountModal = true">Discount</button><button class="btn btn-secondary btn-sm" @click="splitOpen()" x-show="order && order.items.length > 1">Split</button></div>
        <div class="space-y-0.5">
          <div class="flex justify-between text-slate-500" x-show="draft.length"><span>New items (before tax)</span><span x-text="money(draftTotal())"></span></div>
          <template x-if="order"><div><div class="flex justify-between text-slate-500" x-show="order.discount > 0"><span>Discount</span><span x-text="'−' + money(order.discount)"></span></div>
            <div class="flex justify-between text-slate-500"><span>Tax</span><span x-text="money(order.tax)"></span></div>
            <div class="flex justify-between text-lg font-bold"><span>Total</span><span x-text="money(order.total)"></span></div>
            <div class="flex justify-between text-slate-500" x-show="order.paid > 0"><span>Paid</span><span x-text="money(order.paid)"></span></div></div></template>
        </div>
        <div class="grid grid-cols-3 gap-2">
          <button class="btn btn-secondary py-3" :disabled="busy || !draft.length" @click="submit(false)">Hold</button>
          <button class="btn btn-secondary py-3" :disabled="busy || (!draft.length && !(order && order.status === 'open'))" @click="submit(true)">Send</button>
          <button class="btn btn-primary py-3 text-base" :disabled="busy || (!draft.length && (!order || order.balance <= 0))" @click="openPay()">Pay</button>
        </div>
        <button class="w-full text-center text-xs text-red-500" x-show="order && order.status !== 'paid' && order.status !== 'void'" @click="voidOrderModal = true">Void whole ticket</button>
      </div>
    </aside>
  </div>
  <button class="fixed bottom-4 right-4 z-10 rounded-full bg-emerald-600 px-5 py-3 font-semibold text-white shadow-xl lg:hidden" x-show="pane === 'menu' && (draft.length || order)" @click="pane = 'ticket'" x-text="'Ticket · ' + money(order ? order.total + draftTotal() : draftTotal())"></button>

  <!-- pay -->
  <div x-show="payOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-3" @keydown.escape.window="payOpen = false">
    <div class="card w-full max-w-lg space-y-3" x-show="payOpen">
      <div class="flex items-center justify-between"><h2 class="text-lg font-bold">Payment</h2><button class="btn btn-secondary btn-sm" @click="payOpen = false">✕</button></div>
      <p class="text-3xl font-extrabold text-emerald-700" x-text="money(due)"></p>
      <div class="flex flex-wrap gap-1"><template x-for="m in payMethods()" :key="m.key"><button class="btn btn-sm" :class="method === m.type ? 'btn-primary' : 'btn-secondary'" @click="method = m.type" x-text="m.name"></button></template></div>
      <template x-if="method === 'cash'"><div class="space-y-2"><div class="flex flex-wrap gap-2"><template x-for="a in quickAmounts()" :key="a"><button class="btn btn-secondary" @click="tendered = a" x-text="a === due ? 'Exact' : money(a)"></button></template></div>
        <input class="input text-xl" type="number" inputmode="numeric" placeholder="Cash received" x-model.number="tendered"><p class="text-sm" :class="tendered >= due ? 'text-emerald-700' : 'text-slate-500'" x-text="tendered >= due ? 'Change: ' + money(tendered - due) : (tendered ? 'Short by ' + money(due - tendered) : '')"></p></div></template>
      <template x-if="method === 'momo'"><div class="space-y-2"><input class="input text-lg" inputmode="tel" placeholder="MoMo number 2507…" x-model="phone"><p class="text-sm text-slate-500" x-text="momoMsg"></p></div></template>
      <template x-if="['card','bank','other','voucher'].includes(method)"><input class="input" placeholder="Reference / slip number (optional)" x-model="reference"></template>
      <template x-if="method === 'room_charge'"><div class="space-y-2"><input class="input" placeholder="Search in-house guest or room…" x-model="guestQ"><div class="max-h-44 overflow-y-auto rounded-lg border border-slate-200">
        <template x-for="g in inHouseFiltered()" :key="g.id"><button class="block w-full border-b border-slate-100 px-3 py-2 text-left text-sm hover:bg-slate-50" :class="roomPick === g.id ? 'bg-emerald-50' : ''" @click="roomPick = g.id"><b x-text="'Room ' + (g.room || '?')"></b> · <span x-text="g.guest"></span></button></template>
        <p x-show="!inHouseFiltered().length" class="p-3 text-sm text-slate-500">No checked-in guests found.</p></div></div></template>
      <button class="btn btn-primary w-full py-3 text-base" :disabled="busy || !canConfirm()" @click="confirmPay()" x-text="busy ? 'Working…' : (method === 'momo' ? 'Send payment request' : method === 'room_charge' ? 'Charge to room' : 'Confirm payment')"></button>
    </div>
  </div>

  <!-- receipt -->
  <div x-show="receipt" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3"><div class="card w-full max-w-sm space-y-3 text-center" x-show="receipt">
    <p class="text-4xl">✅</p><h2 class="text-xl font-bold">Paid</h2><p class="text-slate-500" x-text="receipt ? receipt.number + ' · ' + money(receipt.total) : ''"></p><p x-show="change > 0" class="text-lg font-semibold text-emerald-700" x-text="'Change: ' + money(change)"></p>
    <div class="grid grid-cols-2 gap-2"><button class="btn btn-secondary" @click="printReceipt()">Print receipt</button><button class="btn btn-primary" @click="receipt = null; newTicket()">Next ticket</button></div></div></div>

  <!-- discount -->
  <div x-show="discountModal" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-3"><form class="card w-full max-w-sm space-y-3" @submit.prevent="applyDiscount()" x-show="discountModal"><h2 class="font-bold">Discount</h2>
    <div class="flex gap-2"><button type="button" class="btn btn-sm" :class="dKind === 'percent' ? 'btn-primary' : 'btn-secondary'" @click="dKind = 'percent'">%</button><button type="button" class="btn btn-sm" :class="dKind === 'amount' ? 'btn-primary' : 'btn-secondary'" @click="dKind = 'amount'">RWF</button></div>
    <input class="input" type="number" min="1" x-model.number="dValue" :placeholder="dKind === 'percent' ? 'Percent' : 'Amount in RWF'" required><input class="input" type="password" inputmode="numeric" maxlength="6" placeholder="Manager PIN (if you cannot approve)" x-model="pin">
    <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="discountModal = false">Cancel</button><button class="btn btn-primary">Apply</button></div></form></div>

  <!-- void line / order -->
  <div x-show="voidLine" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-3"><form class="card w-full max-w-sm space-y-3" @submit.prevent="doVoidLine()" x-show="voidLine"><h2 class="font-bold">Void <span x-text="voidLine?.name"></span></h2>
    <input class="input" placeholder="Reason" x-model="reason" required><input class="input" type="password" inputmode="numeric" maxlength="6" placeholder="Manager PIN (needed after sending)" x-model="pin">
    <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="voidLine = null">Cancel</button><button class="btn btn-danger">Void item</button></div></form></div>
  <div x-show="voidOrderModal" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-3"><form class="card w-full max-w-sm space-y-3" @submit.prevent="doVoidOrder()" x-show="voidOrderModal"><h2 class="font-bold">Void this ticket?</h2>
    <input class="input" placeholder="Reason" x-model="reason" required><input class="input" type="password" inputmode="numeric" maxlength="6" placeholder="Manager PIN" x-model="pin">
    <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="voidOrderModal = false">Cancel</button><button class="btn btn-danger">Void ticket</button></div></form></div>

  <!-- split -->
  <div x-show="splitModal" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-3"><div class="card w-full max-w-md space-y-3" x-show="splitModal"><h2 class="font-bold">Split bill: pick items for the new ticket</h2>
    <template x-for="l in splitLines()" :key="l.id"><label class="flex items-center gap-3 border-b border-slate-100 py-2 text-sm"><input type="checkbox" :value="l.id" x-model="splitPick"><span class="flex-1" x-text="Number(l.quantity) + '× ' + l.name"></span><span x-text="money(l.line_total)"></span></label></template>
    <div class="flex justify-end gap-2"><button class="btn btn-secondary" @click="splitModal = false">Cancel</button><button class="btn btn-primary" :disabled="!splitPick.length || splitPick.length >= splitLines().length" @click="doSplit()">Split</button></div></div></div>

  <!-- shift -->
  <div x-show="shiftModal" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-3"><form class="card w-full max-w-sm space-y-3" @submit.prevent="doShift()" x-show="shiftModal">
    <h2 class="font-bold" x-text="shift ? 'Close cash shift' : 'Open cash shift'"></h2>
    <input class="input" type="number" min="0" x-model.number="shiftAmount" :placeholder="shift ? 'Cash counted in the till (RWF)' : 'Opening cash float (RWF)'" required>
    <p x-show="shiftResult" class="rounded-lg bg-slate-100 p-2 text-sm" x-text="shiftResult"></p>
    <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" @click="shiftModal = false; shiftResult = ''">Close</button><button class="btn btn-primary" x-text="shift ? 'Close shift' : 'Open shift'"></button></div></form></div>
</div>
@endverbatim
</body>
</html>
