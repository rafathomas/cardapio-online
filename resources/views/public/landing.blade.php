@extends('layouts.public')

@section('title', 'Cardápio online para restaurantes — pedidos pelo WhatsApp')
@section('description', 'Crie seu cardápio digital em minutos, compartilhe o link e o QR Code e receba pedidos direto no WhatsApp. Comece grátis, sem cartão de crédito.')
@section('canonical', route('landing'))

@php
    $faqs = [
        ['q' => 'Preciso pagar para começar?', 'a' => 'Não. O plano Free é gratuito para sempre e permite até 10 produtos, link público, QR Code e pedidos pelo WhatsApp. Você só assina o Pro quando o cardápio crescer.'],
        ['q' => 'Meus clientes precisam instalar algum aplicativo?', 'a' => 'Não. O cardápio abre direto no navegador do celular. O cliente monta o pedido e o WhatsApp abre com a mensagem já pronta.'],
        ['q' => 'Vocês cobram comissão sobre os pedidos?', 'a' => 'Nunca. O pedido vai direto do cliente para o seu WhatsApp e o pagamento é combinado entre vocês. Cobramos apenas a assinatura mensal do plano Pro.'],
        ['q' => 'Quanto tempo leva para colocar no ar?', 'a' => 'A maioria dos estabelecimentos publica o cardápio em menos de 15 minutos. Você cria a conta, cadastra as categorias e os produtos, e publica.'],
        ['q' => 'Posso usar meu próprio domínio?', 'a' => 'Cada cardápio recebe um link exclusivo do tipo /cardapio/seu-negocio, que você pode personalizar. Domínio próprio está no nosso roteiro.'],
        ['q' => 'Como funciona o pagamento da assinatura?', 'a' => 'A cobrança é processada pelo Mercado Pago, com Pix ou cartão de crédito. Você pode cancelar quando quiser e continua com acesso até o fim do período pago.'],
    ];
@endphp

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => config('app.name'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => 'Plataforma de cardápio online com pedidos pelo WhatsApp para restaurantes, lanchonetes e pizzarias.',
            'offers' => $plans->map(fn ($plan) => [
                '@type' => 'Offer',
                'name' => $plan->name,
                'price' => $plan->price()->toDecimalString(),
                'priceCurrency' => $plan->currency,
            ])->all(),
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
            ])->all(),
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('body')
<a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-white">
    Pular para o conteúdo
</a>

<header class="sticky top-0 z-40 border-b border-brand-border/60 bg-surface-warm/90 backdrop-blur">
    <nav class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3" aria-label="Principal">
        <a href="{{ route('landing') }}" class="flex items-center gap-2 text-lg font-bold text-ink">
            <span class="flex size-9 items-center justify-center rounded-xl bg-brand text-white">
                <x-icon name="store" class="size-5" />
            </span>
            <span class="font-display">{{ config('app.name') }}</span>
        </a>

        <div class="hidden items-center gap-1 md:flex">
            <a href="#recursos" class="btn-ghost">Recursos</a>
            <a href="#planos" class="btn-ghost">Planos</a>
            <a href="#faq" class="btn-ghost">Dúvidas</a>
        </div>

        <div class="flex items-center gap-2">
            <a href="/app/entrar" class="btn-secondary hidden sm:inline-flex">Entrar</a>
            <a href="/app/criar-conta" class="btn-primary">Criar grátis</a>
        </div>
    </nav>
</header>

<main id="conteudo" class="flex-1">
    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(60rem_40rem_at_70%_-10%,rgba(220,38,38,0.10),transparent)]"></div>

        <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 md:py-24 lg:grid-cols-2">
            <div class="animate-rise">
                <p class="badge bg-brand-soft text-brand-strong ring-1 ring-brand-border">
                    <x-icon name="sparkles" class="size-3.5" />
                    Sem comissão sobre pedidos
                </p>

                <h1 class="mt-5 font-display text-4xl leading-tight text-ink sm:text-5xl lg:text-6xl">
                    Seu cardápio online em poucos minutos.
                </h1>

                <p class="mt-5 max-w-xl text-lg text-ink-muted">
                    Crie seu cardápio, compartilhe o link e receba pedidos pelo WhatsApp.
                    Sem aplicativo para o cliente baixar, sem intermediário levando sua margem.
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="/app/criar-conta" class="btn-primary px-6 py-3 text-base">
                        Criar meu cardápio grátis
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                    <a href="#exemplos" class="btn-secondary px-6 py-3 text-base">Ver um exemplo</a>
                </div>

                <p class="mt-4 text-sm text-ink-muted">
                    Grátis para sempre no plano Free · Não pedimos cartão de crédito
                </p>
            </div>

            {{-- Mockup do cardapio no celular --}}
            <div class="relative mx-auto w-full max-w-xs animate-rise lg:max-w-sm" aria-hidden="true">
                <div class="rounded-[2rem] border-8 border-ink/90 bg-surface shadow-lifted">
                    <div class="rounded-[1.4rem] overflow-hidden">
                        <div class="bg-brand px-4 pt-5 pb-8 text-white">
                            <div class="flex items-center gap-3">
                                <div class="flex size-11 items-center justify-center rounded-xl bg-white/20 font-display text-lg">SP</div>
                                <div>
                                    <p class="font-semibold">Sabor &amp; Ponto</p>
                                    <p class="text-xs text-white/80">Aberto agora · 18h às 23h30</p>
                                </div>
                            </div>
                        </div>

                        <div class="-mt-4 space-y-2.5 rounded-t-2xl bg-surface p-3">
                            @foreach ([['X-Burger Clássico', 'R$ 24,90'], ['X-Bacon Duplo', 'R$ 32,90'], ['Batata Frita P', 'R$ 12,00']] as [$nome, $preco])
                                <div class="flex items-center gap-3 rounded-xl border border-line p-2.5">
                                    <div class="size-12 shrink-0 rounded-lg bg-surface-warm"></div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold">{{ $nome }}</p>
                                        <p class="text-sm font-bold text-brand">{{ $preco }}</p>
                                    </div>
                                    <span class="flex size-8 items-center justify-center rounded-lg bg-brand text-white">
                                        <x-icon name="plus" class="size-4" />
                                    </span>
                                </div>
                            @endforeach

                            <div class="flex items-center justify-between rounded-xl bg-ink px-3.5 py-3 text-white">
                                <span class="text-sm">3 itens · R$ 69,80</span>
                                <span class="text-sm font-semibold">Finalizar</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Como funciona: 3 passos --}}
    <section id="como-funciona" class="border-y border-brand-border/60 bg-surface">
        <div class="mx-auto max-w-6xl px-4 py-16 md:py-20">
            <div class="max-w-2xl">
                <h2 class="font-display text-3xl text-ink sm:text-4xl">Como funciona</h2>
                <p class="mt-3 text-lg text-ink-muted">Três passos entre criar a conta e receber o primeiro pedido.</p>
            </div>

            <ol class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['1', 'Monte o cardápio', 'Crie categorias, cadastre produtos com foto e preço. Leva menos de 15 minutos.'],
                    ['2', 'Publique e compartilhe', 'Você recebe um link exclusivo e um QR Code para imprimir e colar na mesa.'],
                    ['3', 'Receba no WhatsApp', 'O cliente monta o pedido e o WhatsApp abre com a mensagem pronta, item por item.'],
                ] as [$numero, $titulo, $texto])
                    <li class="card p-6">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-brand-soft font-display text-lg font-bold text-brand">{{ $numero }}</span>
                        <h3 class="mt-4 text-lg font-bold text-ink">{{ $titulo }}</h3>
                        <p class="mt-2 text-ink-muted">{{ $texto }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Recursos --}}
    <section id="recursos" class="mx-auto max-w-6xl px-4 py-16 md:py-20">
        <div class="max-w-2xl">
            <h2 class="font-display text-3xl text-ink sm:text-4xl">O que vem junto</h2>
            <p class="mt-3 text-lg text-ink-muted">Tudo que um cardápio digital precisa ter — e nada que você não vá usar.</p>
        </div>

        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['whatsapp', 'Pedido pelo WhatsApp', 'A mensagem chega estruturada: itens, adicionais, observações e total.'],
                ['qr', 'QR Code para a mesa', 'Baixe em PNG ou SVG, imprima e cole. Atualizou o cardápio, o QR continua o mesmo.'],
                ['store', 'Página própria', 'Logo, cores, horário de funcionamento e status aberto/fechado automático.'],
                ['cart', 'Carrinho sem cadastro', 'O cliente não cria conta nem instala nada. Menos atrito, mais pedido fechado.'],
                ['sparkles', 'Adicionais e promoções', 'Queijo, bacon, borda recheada. Preço promocional com o valor antigo riscado.'],
                ['link', 'Link que você escolhe', 'Personalize o endereço do seu cardápio e use na bio do Instagram.'],
            ] as [$icone, $titulo, $texto])
                <div class="card p-6">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-brand-soft text-brand">
                        <x-icon :name="$icone" class="size-5" />
                    </span>
                    <h3 class="mt-4 font-bold text-ink">{{ $titulo }}</h3>
                    <p class="mt-2 text-sm text-ink-muted">{{ $texto }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Para quem e --}}
    <section id="para-quem" class="border-y border-brand-border/60 bg-surface">
        <div class="mx-auto max-w-6xl px-4 py-16 md:py-20">
            <h2 class="font-display text-3xl text-ink sm:text-4xl">Para quem é</h2>
            <p class="mt-3 max-w-2xl text-lg text-ink-muted">
                Feito para quem atende no balcão, na mesa e no delivery próprio.
            </p>

            <ul class="mt-8 flex flex-wrap gap-2.5">
                @foreach (\App\Enums\EstablishmentSegment::cases() as $segmento)
                    @continue($segmento === \App\Enums\EstablishmentSegment::Outro)
                    <li class="rounded-full border border-brand-border bg-brand-soft px-4 py-2 text-sm font-semibold text-brand-strong">
                        {{ $segmento->label() }}
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Exemplos --}}
    <section id="exemplos" class="mx-auto max-w-6xl px-4 py-16 md:py-20">
        <h2 class="font-display text-3xl text-ink sm:text-4xl">Veja um cardápio de verdade</h2>
        <p class="mt-3 max-w-2xl text-lg text-ink-muted">
            Abra no celular para sentir como seu cliente vai usar.
        </p>

        <div class="mt-8 grid gap-5 sm:grid-cols-2">
            @foreach ([['Sabor & Ponto', 'sabor-e-ponto', 'Lanchonete'], ['Forno di Napoli', 'forno-di-napoli', 'Pizzaria']] as [$nome, $slug, $tipo])
                <a href="/cardapio/{{ $slug }}"
                   class="card group flex items-center justify-between gap-4 p-6 transition-shadow duration-200 hover:shadow-lifted">
                    <div>
                        <p class="text-sm font-semibold text-brand">{{ $tipo }}</p>
                        <p class="mt-1 text-xl font-bold text-ink">{{ $nome }}</p>
                        <p class="mt-1 text-sm text-ink-muted">/cardapio/{{ $slug }}</p>
                    </div>
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand transition-transform duration-200 group-hover:translate-x-1">
                        <x-icon name="arrow-right" class="size-5" />
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Planos --}}
    <section id="planos" class="border-y border-brand-border/60 bg-surface">
        <div class="mx-auto max-w-5xl px-4 py-16 md:py-20">
            <div class="max-w-2xl">
                <h2 class="font-display text-3xl text-ink sm:text-4xl">Planos</h2>
                <p class="mt-3 text-lg text-ink-muted">Comece grátis. Assine o Pro quando o cardápio crescer.</p>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-2">
                @foreach ($plans as $plan)
                    @php $isPro = ! $plan->isFree(); @endphp
                    <div @class([
                        'card relative flex flex-col p-7',
                        'ring-2 ring-brand' => $isPro,
                    ])>
                        @if ($isPro)
                            <span class="badge absolute -top-3 left-7 bg-brand text-white">Mais completo</span>
                        @endif

                        <h3 class="font-display text-2xl text-ink">{{ $plan->name }}</h3>
                        <p class="mt-1 text-sm text-ink-muted">{{ $plan->description }}</p>

                        <p class="mt-5 flex items-baseline gap-1">
                            <span class="text-4xl font-bold text-ink">{{ $plan->price()->format() }}</span>
                            @unless ($plan->isFree())
                                <span class="text-ink-muted">/mês</span>
                            @endunless
                        </p>

                        <ul class="mt-6 flex-1 space-y-2.5 text-sm">
                            @foreach ([
                                $plan->max_products === null ? 'Produtos ilimitados' : "Até {$plan->max_products} produtos",
                                $plan->max_categories === null ? 'Categorias ilimitadas' : "Até {$plan->max_categories} categorias",
                                'Link público e QR Code',
                                'Pedidos pelo WhatsApp',
                                'Fotos nos produtos',
                            ] as $item)
                                <li class="flex items-start gap-2.5">
                                    <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" />
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach

                            @foreach (\App\Enums\PlanFeature::cases() as $feature)
                                <li class="flex items-start gap-2.5 {{ $plan->hasFeature($feature->value) ? '' : 'text-slate-400' }}">
                                    @if ($plan->hasFeature($feature->value))
                                        <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" />
                                    @else
                                        <x-icon name="x" class="mt-0.5 size-4 shrink-0" />
                                    @endif
                                    <span>{{ $feature->label() }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <a href="/app/criar-conta?plano={{ $plan->slug }}"
                           class="{{ $isPro ? 'btn-primary' : 'btn-secondary' }} mt-7 w-full py-3">
                            {{ $plan->isFree() ? 'Começar grátis' : 'Assinar o Pro' }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="mx-auto max-w-3xl px-4 py-16 md:py-20">
        <h2 class="font-display text-3xl text-ink sm:text-4xl">Perguntas frequentes</h2>

        <div class="mt-8 divide-y divide-line rounded-card border border-line bg-surface">
            @foreach ($faqs as $faq)
                <details class="group p-5">
                    <summary class="tap flex list-none items-center justify-between gap-4 font-semibold text-ink">
                        {{ $faq['q'] }}
                        <x-icon name="chevron-down" class="size-5 shrink-0 text-ink-muted transition-transform duration-200 group-open:rotate-180" />
                    </summary>
                    <p class="mt-3 text-ink-muted">{{ $faq['a'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- CTA final --}}
    <section class="bg-ink">
        <div class="mx-auto max-w-3xl px-4 py-16 text-center md:py-20">
            <h2 class="font-display text-3xl text-white sm:text-4xl">Coloque seu cardápio no ar hoje</h2>
            <p class="mt-4 text-lg text-white/70">
                Leva menos tempo do que montar uma foto de cardápio no Instagram.
            </p>
            <a href="/app/criar-conta" class="btn mt-8 bg-white px-7 py-3.5 text-base text-ink hover:bg-white/90">
                Criar meu cardápio grátis
                <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>
    </section>
</main>

<footer class="border-t border-brand-border/60 bg-surface-warm">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 text-sm text-ink-muted sm:flex-row sm:items-center sm:justify-between">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        <nav class="flex gap-5" aria-label="Rodapé">
            <a href="#planos" class="hover:text-ink">Planos</a>
            <a href="#faq" class="hover:text-ink">Dúvidas</a>
            <a href="/app/entrar" class="hover:text-ink">Entrar</a>
        </nav>
    </div>
</footer>
@endsection
