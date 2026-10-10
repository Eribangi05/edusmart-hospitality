<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Edusmart Hospitality')</title>
  @vite(['resources/css/app.css', 'resources/js/auth.js'])
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">
<div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
  <aside class="relative hidden overflow-hidden bg-gradient-to-br from-emerald-700 via-emerald-600 to-emerald-800 p-12 text-white lg:flex lg:flex-col lg:justify-between">
    <a href="/" class="flex items-center gap-2 text-lg font-bold"><span class="grid h-9 w-9 place-items-center rounded-lg bg-white/15">E</span> Edusmart Hospitality</a>
    <div>
      <h2 class="max-w-md text-4xl font-extrabold leading-tight">Hospitality, simplified.</h2>
      <ul class="mt-6 space-y-3 text-emerald-50">
        @foreach (['Rooms, restaurant, shop and tours in one platform', 'A workspace built for each job: desk, terminal, kitchen, staff', 'Works offline on Android, syncs when the network is back', 'Cash and MTN MoMo from the first sale'] as $x)<li class="flex gap-3"><span class="mt-1 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-white/20 text-xs">✓</span>{{ $x }}</li>@endforeach
      </ul>
    </div>
    <p class="text-sm text-emerald-100">Kinyarwanda · Français · English</p>
    <div class="pointer-events-none absolute -bottom-24 -right-24 h-80 w-80 rounded-full bg-white/10"></div>
    <div class="pointer-events-none absolute -top-16 right-10 h-40 w-40 rounded-full bg-white/10"></div>
  </aside>
  <div class="flex items-center justify-center px-4">
    <div class="w-full max-w-md py-10">
      <a href="/" class="mb-6 block text-center text-lg font-bold text-emerald-700 lg:hidden">Edusmart Hospitality</a>
      @yield('body')
    </div>
  </div>
</div>
</body>
</html>
