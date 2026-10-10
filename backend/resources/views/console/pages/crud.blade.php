@verbatim
<template x-for="r in ($store.app.route.page === 'crud' ? [$store.app.route.params.resource] : [])" :key="r">
  <div x-data="crud(r)">
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <h1 class="text-2xl font-bold" x-text="title()"></h1>
      <input class="input w-56" :placeholder="$store.app.t('search')" x-model.debounce.300ms="q" @input="load()">
      <div class="flex-1"></div>
      <button x-show="canWrite" class="btn btn-primary" @click="openForm()" x-text="'+ ' + $store.app.t('add')"></button>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50"><tr><template x-for="c in cols" :key="c.k"><th class="th" x-text="$store.app.t(c.k) !== c.k ? $store.app.t(c.k) : c.k.replace(/_id$/,'').replace(/_bp$/,' %').replace(/_/g,' ')"></th></template><th class="th" x-show="canWrite"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <template x-for="row in rows" :key="row.id"><tr class="hover:bg-slate-50">
            <template x-for="c in cols" :key="c.k"><td class="td" x-text="cell(row, c)"></td></template>
            <td class="td whitespace-nowrap text-right" x-show="canWrite"><button class="btn btn-secondary btn-sm" @click="openForm(row)" x-text="$store.app.t('edit')"></button> <button x-show="canDelete" class="btn btn-secondary btn-sm text-red-600" @click="remove(row)" x-text="$store.app.t('delete')"></button></td>
          </tr></template>
          <tr x-show="!loading && !rows.length"><td class="td text-slate-400" :colspan="cols.length + 1" x-text="$store.app.t('no_data')"></td></tr>
        </tbody>
      </table>
    </div>
    <div class="mt-3 flex items-center gap-2 text-sm text-slate-500" x-show="meta.total > meta.per_page">
      <button class="btn btn-secondary btn-sm" :disabled="meta.page <= 1" @click="load(meta.page - 1)">‹</button>
      <span x-text="meta.page + ' / ' + Math.ceil(meta.total / meta.per_page)"></span>
      <button class="btn btn-secondary btn-sm" :disabled="meta.page * meta.per_page >= meta.total" @click="load(meta.page + 1)">›</button>
    </div>
    <div x-show="form" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4" @keydown.escape.window="form = null">
      <form class="card my-8 w-full max-w-xl space-y-3" @submit.prevent="save()" x-show="form">
        <h2 class="text-lg font-semibold" x-text="(editing ? $store.app.t('edit') : $store.app.t('add')) + ' · ' + title()"></h2>
        <p x-show="error" x-text="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
        <template x-if="form"><div class="grid gap-3 sm:grid-cols-2">
          <template x-for="f in allFields()" :key="f.k">
            <label class="block text-sm font-medium" :class="['textarea','json','tags','i18n'].includes(f.type) ? 'sm:col-span-2' : ''">
              <span x-text="fl(f) + (f.req ? ' *' : '')"></span>
              <template x-if="!f.type || ['text','email'].includes(f.type)"><input class="input mt-1" :type="f.type === 'email' ? 'email' : 'text'" x-model="form[f.k]" :required="!!f.req"></template>
              <template x-if="['number','money','bp'].includes(f.type)"><input class="input mt-1" type="number" step="any" min="0" x-model="form[f.k]" :required="!!f.req"></template>
              <template x-if="f.type === 'date'"><input class="input mt-1" type="date" x-model="form[f.k]" :required="!!f.req"></template>
              <template x-if="f.type === 'time'"><input class="input mt-1" type="time" x-model="form[f.k]" :required="!!f.req"></template>
              <template x-if="f.type === 'datetime'"><input class="input mt-1" type="datetime-local" x-model="form[f.k]"></template>
              <template x-if="f.type === 'textarea'"><textarea class="input mt-1" rows="3" x-model="form[f.k]"></textarea></template>
              <template x-if="f.type === 'json'"><textarea class="input mt-1 font-mono text-xs" rows="4" x-model="form[f.k]"></textarea></template>
              <template x-if="f.type === 'tags'"><input class="input mt-1" placeholder="a, b, c" x-model="form[f.k]"></template>
              <template x-if="f.type === 'bool'"><span class="mt-1 block"><input type="checkbox" class="rounded border-slate-300" x-model="form[f.k]"></span></template>
              <template x-if="f.type === 'select'"><select class="input mt-1" x-model="form[f.k]" :required="!!f.req"><option value=""></option><template x-for="o in f.opts" :key="o.value"><option :value="o.value" x-text="o.label"></option></template></select></template>
              <template x-if="f.type === 'ref'"><select class="input mt-1" x-model="form[f.k]" :required="!!f.req"><option value=""></option><template x-for="o in ref(f)" :key="o.id"><option :value="o.id" x-text="refLabel(o)"></option></template></select></template>
              <template x-if="f.type === 'i18n'"><span class="mt-1 grid gap-2 sm:grid-cols-3"><template x-for="l in ['en','fr','rw']" :key="l"><input class="input" :placeholder="l" x-model="form[f.k][l]"></template></span></template>
              <span x-show="f.hint" class="mt-0.5 block text-xs font-normal text-slate-400" x-text="f.hint"></span>
            </label>
          </template>
        </div></template>
        <div class="flex justify-end gap-2 pt-2"><button type="button" class="btn btn-secondary" @click="form = null" x-text="$store.app.t('cancel')"></button><button class="btn btn-primary" :disabled="saving" x-text="$store.app.t('save')"></button></div>
      </form>
    </div>
  </div>
</template>

<template x-for="r in (['menu','services'].includes($store.app.route.page) ? [$store.app.route.page] : [])" :key="r">
  <div x-data="crud('catalog-items', catalogOpts(r === 'menu' ? 'menu' : 'tour'))">
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <h1 class="text-2xl font-bold" x-text="title()"></h1>
      <input class="input w-56" :placeholder="$store.app.t('search')" x-model.debounce.300ms="q" @input="load()">
      <div class="flex-1"></div>
      <button x-show="canWrite" class="btn btn-primary" @click="openForm()" x-text="'+ ' + $store.app.t('add')"></button>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50"><tr><template x-for="c in cols" :key="c.k"><th class="th" x-text="c.k.replace(/_/g,' ')"></th></template><th class="th"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <template x-for="row in rows" :key="row.id"><tr class="hover:bg-slate-50">
            <template x-for="c in cols" :key="c.k"><td class="td" x-text="cell(row, c)"></td></template>
            <td class="td whitespace-nowrap text-right"><button x-show="canWrite" class="btn btn-secondary btn-sm" @click="openForm(row)" x-text="$store.app.t('edit')"></button> <button x-show="canDelete" class="btn btn-secondary btn-sm text-red-600" @click="remove(row)" x-text="$store.app.t('delete')"></button></td>
          </tr></template>
          <tr x-show="!loading && !rows.length"><td class="td text-slate-400" :colspan="cols.length + 1" x-text="$store.app.t('no_data')"></td></tr>
        </tbody>
      </table>
    </div>
    <div x-show="form" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4" @keydown.escape.window="form = null">
      <form class="card my-8 w-full max-w-xl space-y-3" @submit.prevent="save()" x-show="form">
        <h2 class="text-lg font-semibold" x-text="(editing ? $store.app.t('edit') : $store.app.t('add')) + ' · ' + title()"></h2>
        <p x-show="error" x-text="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
        <template x-if="form"><div class="grid gap-3 sm:grid-cols-2">
          <template x-for="f in allFields()" :key="f.k">
            <label class="block text-sm font-medium" :class="['textarea','json'].includes(f.type) ? 'sm:col-span-2' : ''">
              <span x-text="fl(f) + (f.req ? ' *' : '')"></span>
              <template x-if="!f.type"><input class="input mt-1" x-model="form[f.k]" :required="!!f.req"></template>
              <template x-if="['number','money'].includes(f.type)"><input class="input mt-1" type="number" min="0" x-model="form[f.k]" :required="!!f.req"></template>
              <template x-if="f.type === 'textarea'"><textarea class="input mt-1" rows="2" x-model="form[f.k]"></textarea></template>
              <template x-if="f.type === 'json'"><textarea class="input mt-1 font-mono text-xs" rows="4" x-model="form[f.k]"></textarea></template>
              <template x-if="f.type === 'bool'"><span class="mt-1 block"><input type="checkbox" class="rounded border-slate-300" x-model="form[f.k]"></span></template>
              <template x-if="f.type === 'select'"><select class="input mt-1" x-model="form[f.k]"><template x-for="o in f.opts" :key="o.value"><option :value="o.value" x-text="o.label"></option></template></select></template>
              <template x-if="f.type === 'ref'"><select class="input mt-1" x-model="form[f.k]"><option value=""></option><template x-for="o in ref(f)" :key="o.id"><option :value="o.id" x-text="refLabel(o)"></option></template></select></template>
              <span x-show="f.hint" class="mt-0.5 block text-xs font-normal text-slate-400" x-text="f.hint"></span>
            </label>
          </template>
        </div></template>
        <div class="flex justify-end gap-2 pt-2"><button type="button" class="btn btn-secondary" @click="form = null" x-text="$store.app.t('cancel')"></button><button class="btn btn-primary" :disabled="saving" x-text="$store.app.t('save')"></button></div>
      </form>
    </div>
  </div>
</template>
@endverbatim
