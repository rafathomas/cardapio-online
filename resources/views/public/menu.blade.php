@extends('layouts.public')

@php
    $descricao = $establishment->description
        ?: "Cardápio online de {$establishment->name}. Peça pelo WhatsApp.";
    $imagem = $establishment->coverUrl() ?? $establishment->logoUrl();
@endphp

@section('title', $establishment->name.' — Cardápio online')
@section('description', Str::limit($descricao, 155))
@section('canonical', $establishment->publicUrl())
@section('og_type', 'restaurant.restaurant')
@section('theme_color', $establishment->primary_color)
@section('body_class', 'bg-surface-muted')

@if (! $establishment->is_indexable)
    @section('robots', 'noindex, nofollow')
@endif

@if ($imagem)
    @section('og_image', $imagem)
@endif

@push('head')
    {{-- Cores do estabelecimento sobrescrevem os tokens da marca. --}}
    <style>
        :root {
            --color-brand: {{ $establishment->primary_color }};
            --color-brand-strong: color-mix(in srgb, {{ $establishment->primary_color }} 85%, black);
            --color-ink: {{ $establishment->secondary_color }};
        }
    </style>

    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Restaurant',
        'name' => $establishment->name,
        'description' => $descricao,
        'url' => $establishment->publicUrl(),
        'image' => array_values(array_filter([$establishment->coverUrl(), $establishment->logoUrl()])),
        'telephone' => $establishment->phone,
        'servesCuisine' => $establishment->segment->label(),
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => trim(($establishment->address_street ?? '').' '.($establishment->address_number ?? '')) ?: null,
            'addressLocality' => $establishment->address_city,
            'addressRegion' => $establishment->address_state,
            'postalCode' => $establishment->address_zipcode,
            'addressCountry' => 'BR',
        ]),
        'openingHoursSpecification' => $establishment->businessHours
            ->where('is_closed', false)
            ->map(fn ($h) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$h->weekday],
                'opens' => substr((string) $h->opens_at, 0, 5),
                'closes' => substr((string) $h->closes_at, 0, 5),
            ])->values()->all(),
        'hasMenu' => [
            '@type' => 'Menu',
            'hasMenuSection' => $sections->map(fn ($section) => [
                '@type' => 'MenuSection',
                'name' => $section['name'],
                'hasMenuItem' => $section['products']->map(fn ($product) => array_filter([
                    '@type' => 'MenuItem',
                    'name' => $product->name,
                    'description' => $product->description,
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => $product->effectivePrice()->toDecimalString(),
                        'priceCurrency' => 'BRL',
                    ],
                ]))->values()->all(),
            ])->values()->all(),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('body')
<a href="#cardapio" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-white">
    Pular para o cardápio
</a>

<header>
    @if ($establishment->coverUrl())
        <img src="{{ $establishment->coverUrl() }}" alt=""
             class="h-32 w-full object-cover sm:h-48" width="1280" height="320" fetchpriority="high">
    @else
        <div class="h-24 w-full bg-brand sm:h-32" aria-hidden="true"></div>
    @endif

    <div class="mx-auto -mt-10 max-w-2xl px-4">
        <div class="card p-5">
            <div class="flex items-start gap-4">
                @if ($establishment->logoUrl())
                    <img src="{{ $establishment->logoUrl() }}" alt="Logo de {{ $establishment->name }}"
                         class="size-16 shrink-0 rounded-xl object-cover ring-1 ring-line"
                         width="64" height="64" fetchpriority="high">
                @else
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-xl bg-brand font-display text-xl text-white">
                        {{ Str::of($establishment->name)->substr(0, 2)->upper() }}
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <h1 class="font-display text-2xl leading-tight text-ink">{{ $establishment->name }}</h1>

                    @if ($establishment->description)
                        <p class="mt-1 text-sm text-ink-muted">{{ $establishment->description }}</p>
                    @endif

                    <p class="mt-2.5">
                        @if ($isOpen)
                            <span class="badge bg-success-soft text-success">
                                <span class="size-1.5 rounded-full bg-success"></span>
                                Aberto agora
                            </span>
                        @else
                            <span class="badge bg-surface-muted text-ink-muted">
                                <span class="size-1.5 rounded-full bg-slate-400"></span>
                                Fechado no momento
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            <dl class="mt-4 space-y-1.5 border-t border-line pt-4 text-sm text-ink-muted">
                @php
                    $hoje = $establishment->businessHours->firstWhere('weekday', (int) now($establishment->timezone)->dayOfWeek);
                @endphp
                @if ($hoje && ! $hoje->is_closed)
                    <div class="flex items-center gap-2">
                        <x-icon name="clock" class="size-4 shrink-0" />
                        <dt class="sr-only">Horário de hoje</dt>
                        <dd>Hoje: {{ substr((string) $hoje->opens_at, 0, 5) }} às {{ substr((string) $hoje->closes_at, 0, 5) }}</dd>
                    </div>
                @endif

                @if ($establishment->address_street)
                    <div class="flex items-center gap-2">
                        <x-icon name="map-pin" class="size-4 shrink-0" />
                        <dt class="sr-only">Endereço</dt>
                        <dd>{{ $establishment->address_street }}, {{ $establishment->address_number }} — {{ $establishment->address_district }}</dd>
                    </div>
                @endif

                @if ($establishment->instagram)
                    <div class="flex items-center gap-2">
                        <x-icon name="instagram" class="size-4 shrink-0" />
                        <dt class="sr-only">Instagram</dt>
                        <dd>
                            <a href="https://instagram.com/{{ ltrim($establishment->instagram, '@') }}"
                               rel="noopener nofollow" target="_blank" class="hover:text-brand">
                                {{ '@'.ltrim($establishment->instagram, '@') }}
                            </a>
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>
</header>

{{-- Navegacao por categoria: rolagem horizontal com scroll-snap. --}}
@if ($sections->count() > 1)
    <nav class="sticky top-0 z-30 mt-5 border-y border-line bg-surface/95 backdrop-blur" aria-label="Categorias">
        <ul class="mx-auto flex max-w-2xl snap-x gap-2 overflow-x-auto px-4 py-2.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($sections as $section)
                <li class="snap-start">
                    <a href="#categoria-{{ $section['slug'] }}"
                       class="tap inline-flex items-center rounded-full border border-line px-4 py-1.5 text-sm font-semibold whitespace-nowrap hover:border-brand hover:text-brand">
                        {{ $section['name'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif

<main id="cardapio" class="mx-auto w-full max-w-2xl flex-1 px-4 pt-6 pb-32">
    @forelse ($sections as $section)
        <section id="categoria-{{ $section['slug'] }}" class="mb-9 scroll-mt-16">
            <h2 class="font-display text-xl text-ink">{{ $section['name'] }}</h2>
            @if ($section['description'])
                <p class="mt-1 text-sm text-ink-muted">{{ $section['description'] }}</p>
            @endif

            <ul class="mt-4 space-y-3">
                @foreach ($section['products'] as $product)
                    <li>
                        <article class="card flex gap-3 p-3">
                            @if ($product->imageUrl())
                                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}"
                                     class="size-20 shrink-0 rounded-xl object-cover sm:size-24"
                                     width="96" height="96" loading="lazy" decoding="async">
                            @endif

                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-semibold text-ink">{{ $product->name }}</h3>
                                    @if ($product->is_featured)
                                        <span class="badge shrink-0 bg-warning-soft text-warning">Destaque</span>
                                    @endif
                                </div>

                                @if ($product->description)
                                    <p class="mt-1 line-clamp-2 text-sm text-ink-muted">{{ $product->description }}</p>
                                @endif

                                <div class="mt-auto flex items-end justify-between gap-3 pt-2">
                                    <p class="flex flex-wrap items-baseline gap-x-2">
                                        <span class="font-bold text-brand">{{ $product->effectivePrice()->format() }}</span>
                                        @if ($product->hasDiscount())
                                            <span class="text-sm text-slate-400 line-through">{{ $product->price()->format() }}</span>
                                            <span class="badge bg-success-soft text-success">-{{ $product->discountPercentage() }}%</span>
                                        @endif
                                    </p>

                                    <button type="button"
                                            data-add-product="{{ $product->id }}"
                                            @disabled(! $isOpen)
                                            class="btn-primary shrink-0 px-3 py-2 disabled:bg-slate-300"
                                            aria-label="Adicionar {{ $product->name }} ao carrinho">
                                        <x-icon name="plus" class="size-4" />
                                        <span class="sr-only sm:not-sr-only">Adicionar</span>
                                    </button>
                                </div>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="card p-8 text-center text-ink-muted">
            Este cardápio ainda não tem produtos publicados.
        </p>
    @endforelse

    @unless ($isOpen)
        <p class="card border-warning/30 bg-warning-soft p-4 text-center text-sm font-medium text-warning">
            Estamos fechados agora. Você pode ver o cardápio, mas os pedidos abrem no próximo horário de atendimento.
        </p>
    @endunless
</main>

@if ($showBranding)
    <footer class="border-t border-line bg-surface px-4 py-6 text-center text-sm text-ink-muted">
        <a href="{{ route('landing') }}" class="hover:text-brand">
            Cardápio digital por <strong class="font-semibold">{{ config('app.name') }}</strong>
        </a>
    </footer>
@endif

{{-- Ilha React: carrinho, adicionais e finalizacao. --}}
<div id="cart-island"
     data-slug="{{ $establishment->slug }}"
     data-open="{{ $isOpen ? '1' : '0' }}"></div>

@push('body_end')
<script type="application/json" id="menu-data">@json($menuData)</script>
@viteReactRefresh
@vite(['resources/js/menu/main.tsx'])
@endpush
@endsection
