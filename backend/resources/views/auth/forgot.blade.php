@extends('layouts.public')
@section('title', 'Reset password · Edusmart Hospitality')
@section('body')
<main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4" x-data="forgotForm()">
  <a href="/" class="mb-6 text-center text-lg font-semibold text-emerald-700">Edusmart Hospitality</a>
  <form @submit.prevent="submit" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold">Reset your password</h1>
    <p x-show="done" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">If that email has an account, a reset link is on its way.</p>
    <p x-show="error" x-text="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
    <label class="block text-sm font-medium">Email<input x-model="email" type="email" required autocomplete="username" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
    <button :disabled="busy" class="w-full rounded-lg bg-emerald-600 py-2.5 font-medium text-white hover:bg-emerald-700 disabled:opacity-60">Send reset link</button>
    <p class="text-center text-sm"><a class="text-emerald-700 underline" href="/login">Back to log in</a></p>
  </form>
</main>
@vite(['resources/js/auth.js'])
@endsection
