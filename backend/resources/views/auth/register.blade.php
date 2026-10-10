@extends('layouts.public')
@section('title', 'Create your company · Edusmart Hospitality')
@section('body')
<main class="mx-auto max-w-xl px-4 py-10" x-data="registerForm()">
  <a href="/" class="mb-6 block text-center text-lg font-semibold text-emerald-700">Edusmart Hospitality</a>
  <form @submit.prevent="submit" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold">Start your free 30-day trial</h1>
    <p x-show="error" x-text="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium sm:col-span-2">Company name<input x-model="f.company" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
      <label class="block text-sm font-medium">What do you run?
        <select x-model="f.property_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          <option value="hotel">Hotel / lodge</option><option value="guesthouse">Guest house</option><option value="restaurant">Restaurant</option>
          <option value="bar">Bar</option><option value="shop">Shop</option><option value="tour_operator">Tour operator</option><option value="other">Other</option>
        </select></label>
      <label class="block text-sm font-medium">Language
        <select x-model="f.locale" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="en">English</option><option value="fr">Français</option><option value="rw">Kinyarwanda</option></select></label>
      <label class="block text-sm font-medium">Your name<input x-model="f.name" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
      <label class="block text-sm font-medium">Phone<input x-model="f.phone" type="tel" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
      <label class="block text-sm font-medium">Email<input x-model="f.email" type="email" required autocomplete="username" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
      <label class="block text-sm font-medium">Password (10+ characters)<input x-model="f.password" type="password" minlength="10" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
    </div>
    <fieldset>
      <legend class="text-sm font-medium">Modules you need (change anytime)</legend>
      <div class="mt-2 grid gap-2 sm:grid-cols-2">
        @foreach ($modules as $m)
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" value="{{ $m }}" x-model="f.modules" class="rounded border-slate-300"> <span class="capitalize">{{ str_replace('_',' ', $m) }}</span></label>
        @endforeach
      </div>
    </fieldset>
    <button :disabled="busy" class="w-full rounded-lg bg-emerald-600 py-2.5 font-medium text-white hover:bg-emerald-700 disabled:opacity-60">Create company</button>
    <p class="text-center text-sm text-slate-600">Already registered? <a class="text-emerald-700 underline" href="/login">Log in</a></p>
  </form>
</main>
@vite(['resources/js/auth.js'])
@endsection
