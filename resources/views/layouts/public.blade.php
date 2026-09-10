<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('description', 'Crie seu cardápio online, compartilhe o link e receba pedidos pelo WhatsApp.')">

    <link rel="canonical" href="@yield('canonical', url()->current())">
    @hasSection('robots')
        <meta name="robots" content="@yield('robots')">
    @endif

    {{-- Open Graph --}}
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="@yield('og_title', View::getSection('title', config('app.name')))">
    <meta property="og:description" content="@yield('og_description', View::getSection('description', 'Seu cardápio online em poucos minutos.'))">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:locale" content="pt_BR">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif

    <meta name="theme-color" content="@yield('theme_color', '#DC2626')">

    @stack('head')

    @vite(['resources/css/app.css'])
    @stack('scripts')
</head>
<body class="flex min-h-full flex-col @yield('body_class', 'bg-surface-warm')">
    @yield('body')

    @stack('body_end')
</body>
</html>
