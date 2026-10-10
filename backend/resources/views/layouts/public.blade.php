<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Edusmart Hospitality')</title>
  <meta name="description" content="@yield('description', 'Run your hotel, restaurant, bar, shop and tours from one platform. Works offline on Android and in the browser.')">
  @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased">
@yield('body')
</body>
</html>
