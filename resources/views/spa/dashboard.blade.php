<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- O painel é área logada: não deve ser indexado. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>Painel — {{ config('app.name') }}</title>
    <meta name="theme-color" content="#DC2626">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/dashboard/main.tsx'])
</head>
<body class="h-full bg-surface-muted">
    <div id="app" data-app-name="{{ config('app.name') }}" class="h-full"></div>
    <noscript>
        <p style="padding:2rem;text-align:center">
            O painel precisa de JavaScript ativado. O seu cardápio público continua funcionando normalmente.
        </p>
    </noscript>
</body>
</html>
