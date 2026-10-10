@verbatim
<template x-if="$store.app.route.page === 'staff.attendance'">
  <div x-data="attendancePage()">
    <div class="mb-4 flex flex-wrap items-center gap-3"><h1 class="text-2xl font-bold" x-text="$store.app.t('attendance')"></h1>
      <input type="date" class="input w-auto" x-model="from" @change="load()"><input type="date" class="input w-auto" x-model="to" @change="load()"><label class="flex items-center gap-1 text-sm"><input type="checkbox" x-model="flagged" @change="load()"> Flagged only</label></div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th class="th">Employee</th><th class="th">Type</th><th class="th">Time</th><th class="th">Flag</th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="e in rows" :key="e.id"><tr><td class="td" x-text="emp(e.employee_id)"></td><td class="td uppercase" x-text="e.type"></td><td class="td" x-text="(e.at || '').slice(0,16).replace('T',' ')"></td><td class="td text-red-600" x-text="e.flagged ? 'review' : ''"></td></tr></template>
      <tr x-show="!loading && !rows.length"><td class="td text-slate-400" colspan="4" x-text="$store.app.t('no_data')"></td></tr></tbody></table></div>
  </div>
</template>
<template x-if="$store.app.route.page === 'staff.payroll'">
  <div x-data="payrollPage()">
    <div class="mb-4 flex flex-wrap items-center gap-3"><h1 class="text-2xl font-bold" x-text="$store.app.t('payroll')"></h1>
      <input type="date" class="input w-auto" x-model="from"><input type="date" class="input w-auto" x-model="to"><button class="btn btn-primary" @click="run()" :disabled="loading">Calculate</button><button class="btn btn-secondary" @click="csv()" x-text="$store.app.t('export')"></button></div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th class="th" x-text="$store.app.t('name')"></th><th class="th">Position</th><th class="th">Type</th><th class="th">Days</th><th class="th">Hours</th><th class="th" x-text="$store.app.t('amount')"></th></tr></thead>
      <tbody class="divide-y divide-slate-100"><template x-for="r in rows" :key="r.employee_id"><tr><td class="td" x-text="r.name"></td><td class="td" x-text="r.position"></td><td class="td" x-text="r.pay_type"></td><td class="td" x-text="r.days"></td><td class="td" x-text="r.hours"></td><td class="td" x-text="money(r.amount)"></td></tr></template></tbody>
      <tfoot><tr><td class="td font-bold" colspan="5" x-text="$store.app.t('total')"></td><td class="td font-bold" x-text="money(total)"></td></tr></tfoot></table></div>
  </div>
</template>
@endverbatim
