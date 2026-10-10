@extends('layouts.public')
@section('title', 'Privacy policy · Edusmart Hospitality')
@section('body')
<main class="mx-auto max-w-3xl px-4 py-10">
  <a href="/" class="text-emerald-700 underline">&larr; Home</a>
  <h1 class="mt-4 text-3xl font-bold">Privacy policy</h1>
  <p class="mt-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">Template text. Have this reviewed by a legal professional familiar with Rwandan data-protection and tax law before launch, then replace it.</p>
  <div class="prose mt-6 space-y-4 text-slate-700">
    @if ('privacy' === 'privacy')
      <p>Edusmart Hospitality processes business and guest data on behalf of each company (the data controller). We store it in isolated company workspaces, encrypt it in transit, back it up daily and never sell it.</p>
      <p>Companies can export or delete their data on request. Staff accounts can be deactivated at any time, and lost devices can be revoked remotely.</p>
      <p>We use cookies only for sign-in sessions. Payment card details are never stored; mobile-money requests go to the operator.</p>
      <p>Contact: privacy@edusmartconsult.com</p>
    @else
      <p>By creating a company you agree to use the service lawfully. Subscriptions renew monthly; unpaid accounts move to a grace period and then read-only access. Data stays available for export.</p>
      <p>The service is provided as is; we aim for high availability but do not guarantee uninterrupted operation. You are responsible for the accuracy of tax settings and receipts issued to your customers.</p>
      <p>Contact: support@edusmartconsult.com</p>
    @endif
  </div>
</main>
@endsection
