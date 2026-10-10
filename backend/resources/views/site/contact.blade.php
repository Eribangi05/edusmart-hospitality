@extends('layouts.site')
@section('title', 'Contact · Edusmart Hospitality')
@section('body')
<section class="mx-auto grid max-w-7xl gap-10 px-4 py-14 lg:grid-cols-2">
  <div><h1 class="text-4xl font-extrabold">Talk to us</h1><p class="mt-3 text-lg text-slate-600">Questions, a demo, or help moving from paper or spreadsheets? We reply within one business day.</p>
    <ul class="mt-6 space-y-3 text-slate-700"><li>🌐 <a class="underline" href="https://edusmartconsult.com">edusmartconsult.com</a></li><li>✉ support@edusmartconsult.com</li><li>🕘 Mon–Fri, Kigali time</li></ul></div>
  <form class="card space-y-3" x-data="contactForm()" @submit.prevent="submit">
    <p x-show="done" x-text="done" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status"></p>
    <p x-show="error" x-text="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
    <div class="grid gap-3 sm:grid-cols-2">
      <label class="text-sm font-medium">Your name<input class="input mt-1" x-model="f.name" required></label>
      <label class="text-sm font-medium">Email<input type="email" class="input mt-1" x-model="f.email" required></label>
      <label class="text-sm font-medium">Phone<input class="input mt-1" x-model="f.phone"></label>
      <label class="text-sm font-medium">Company<input class="input mt-1" x-model="f.company"></label>
      <label class="text-sm font-medium sm:col-span-2">I run a<select class="input mt-1" x-model="f.interest"><option value="">Choose…</option><option>Hotel / lodge</option><option>Restaurant / bar</option><option>Shop</option><option>Tour operator</option><option>Group of properties</option><option>Other</option></select></label>
      <label class="text-sm font-medium sm:col-span-2">How can we help?<textarea class="input mt-1" rows="4" x-model="f.message" required></textarea></label>
    </div>
    <button class="btn btn-primary w-full" :disabled="busy">Send message</button>
  </form>
</section>
@endsection
