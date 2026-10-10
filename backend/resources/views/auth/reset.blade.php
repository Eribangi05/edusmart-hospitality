@extends('layouts.auth')
@section('title', 'Choose a new password · Edusmart Hospitality')
@section('body')
<main x-data="resetForm('{{ $token }}', '{{ e($email) }}')">
  
  <form @submit.prevent="submit" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold">Choose a new password</h1>
    <p x-show="error" x-text="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
    <label class="block text-sm font-medium">Email<input x-model="email" type="email" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm font-medium">New password (10+ characters)<input x-model="password" type="password" minlength="10" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
    <button :disabled="busy" class="w-full rounded-lg bg-emerald-600 py-2.5 font-medium text-white hover:bg-emerald-700 disabled:opacity-60">Update password</button>
  </form>
</main>
@endsection
