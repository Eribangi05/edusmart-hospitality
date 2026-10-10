<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Edusmart Hospitality')</title>
  <meta name="description" content="@yield('description', 'Run your hotel, restaurant, bar, shop and tours from one platform. Works offline on Android and in the browser.')">
  <meta property="og:title" content="@yield('title', 'Edusmart Hospitality')">
  @vite(['resources/css/app.css', 'resources/js/site.js'])
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased" x-data="site()">
<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
  <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3">
    <a href="/" class="flex items-center gap-2 text-lg font-bold text-emerald-700"><span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-600 text-white">E</span> Edusmart Hospitality</a>
    <nav class="ml-6 hidden items-center gap-5 text-sm font-medium text-slate-600 md:flex" aria-label="Main">
      @foreach (['/features' => 'Features', '/solutions' => 'Solutions', '/pricing' => 'Pricing', '/contact' => 'Contact'] as $u => $l)
        <a href="{{ $u }}" class="hover:text-slate-900 {{ request()->is(ltrim($u, '/')) ? 'text-emerald-700' : '' }}">{{ $l }}</a>
      @endforeach
    </nav>
    <div class="flex-1"></div>
    <button class="btn btn-secondary btn-sm" @click="toggleTheme()" aria-label="Toggle dark mode"><span x-text="dark ? '☀' : '☾'"></span></button>
    <a href="/login" class="hidden text-sm font-medium text-slate-700 sm:inline">Log in</a>
    <a href="/register" class="btn btn-primary">Start free trial</a>
    <button class="btn btn-secondary md:hidden" @click="menu = !menu" aria-label="Menu">☰</button>
  </div>
  <nav x-show="menu" x-cloak class="border-t border-slate-200 px-4 py-3 text-sm md:hidden">
    @foreach (['/features' => 'Features', '/solutions' => 'Solutions', '/pricing' => 'Pricing', '/contact' => 'Contact', '/login' => 'Log in'] as $u => $l)<a href="{{ $u }}" class="block py-2">{{ $l }}</a>@endforeach
  </nav>
</header>
<main>@yield('body')</main>
<footer class="mt-20 border-t border-slate-200 bg-slate-50">
  <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
    <div><p class="font-bold text-emerald-700">Edusmart Hospitality</p><p class="mt-2 text-sm text-slate-600">One platform for hotels, restaurants, bars, shops and tour operators in Rwanda.</p>
      <p class="mt-3 text-xs text-slate-500">Ururimi · Langues · Languages: Kinyarwanda · Français · English</p></div>
    <div><p class="text-sm font-semibold">Product</p><ul class="mt-2 space-y-1 text-sm text-slate-600"><li><a href="/features">Features</a></li><li><a href="/solutions">Solutions</a></li><li><a href="/pricing">Pricing</a></li><li><a href="/register">Free trial</a></li></ul></div>
    <div><p class="text-sm font-semibold">Company</p><ul class="mt-2 space-y-1 text-sm text-slate-600"><li><a href="https://edusmartconsult.com">Edusmart Consult</a></li><li><a href="/contact">Contact</a></li><li><a href="/privacy">Privacy</a></li><li><a href="/terms">Terms</a></li></ul></div>
    <div><p class="text-sm font-semibold">Get started</p><p class="mt-2 text-sm text-slate-600">30-day free trial. No card needed.</p><a href="/register" class="btn btn-primary mt-3">Create your company</a></div>
  </div>
  <p class="border-t border-slate-200 py-4 text-center text-xs text-slate-500">&copy; {{ date('Y') }} Edusmart Consult. All rights reserved.</p>
</footer>
</body>
</html>
