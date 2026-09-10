<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Jobs\RecordMenuView;
use App\Models\Establishment;
use App\Models\Product;
use App\Services\Plans\PlanGate;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Cardapio publico renderizado no servidor.
 *
 * A pagina e HTML pronto (bom para SEO e para o primeiro carregamento no
 * celular); apenas o carrinho e hidratado por um componente React.
 */
class MenuController extends Controller
{
    public function __construct(private readonly PlanGate $plans) {}

    public function show(Request $request, string $slug): View
    {
        $activeProducts = fn ($query) => $query->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        $establishment = Establishment::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with([
                'businessHours' => fn ($q) => $q->orderBy('weekday'),
                'categories' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
                'categories.products' => $activeProducts,
                'categories.products.addonGroups' => fn ($q) => $q->where('is_active', true),
                'categories.products.addonGroups.addons' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'uncategorizedProducts' => $activeProducts,
                'uncategorizedProducts.addonGroups' => fn ($q) => $q->where('is_active', true),
                'uncategorizedProducts.addonGroups.addons' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'subscription.plan',
            ])
            ->firstOrFail();

        // Vai para a fila: contabilizar visualizacao nao pode atrasar a pagina.
        RecordMenuView::dispatch($establishment->id, now()->toDateString());

        $sections = $this->sections($establishment);

        return view('public.menu', [
            'establishment' => $establishment,
            'sections' => $sections,
            'isOpen' => $establishment->isOpen(),
            'showBranding' => ! $this->plans->hasFeature($establishment, PlanFeature::RemoveBranding),
            'menuData' => $this->menuPayload($establishment, $sections),
        ]);
    }

    /**
     * Secoes exibidas no cardapio: as categorias ativas mais, ao final, os
     * produtos sem categoria — que de outra forma ficariam invisiveis.
     *
     * @return Collection<int, array{name: string, slug: string, description: string|null, products: Collection<int, Product>}>
     */
    private function sections(Establishment $establishment): Collection
    {
        $sections = $establishment->categories
            ->map(fn ($category): array => [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'products' => $category->products,
            ])
            ->filter(fn (array $section): bool => $section['products']->isNotEmpty())
            ->values();

        if ($establishment->uncategorizedProducts->isNotEmpty()) {
            $sections->push([
                'name' => 'Outros',
                'slug' => 'outros',
                'description' => null,
                'products' => $establishment->uncategorizedProducts,
            ]);
        }

        return $sections;
    }

    /** Payload embutido na pagina: evita um segundo round-trip no celular. */
    private function menuPayload(Establishment $establishment, Collection $sections): array
    {
        return [
            'establishment' => [
                'id' => $establishment->id,
                'name' => $establishment->name,
                'slug' => $establishment->slug,
                'is_open' => $establishment->isOpen(),
                'primary_color' => $establishment->primary_color,
                'secondary_color' => $establishment->secondary_color,
                'whatsapp' => $establishment->whatsappNumber(),
            ],
            'categories' => $sections->map(fn (array $section): array => [
                'id' => $section['slug'],
                'name' => $section['name'],
                'slug' => $section['slug'],
                'products' => $section['products']->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price_cents' => $product->effectivePrice()->cents,
                    'price_formatted' => $product->effectivePrice()->format(),
                    'original_price_formatted' => $product->hasDiscount() ? $product->price()->format() : null,
                    'has_discount' => $product->hasDiscount(),
                    'image_url' => $product->imageUrl(),
                    'is_featured' => $product->is_featured,
                    'addon_groups' => $product->addonGroups->map(fn ($group): array => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'is_required' => $group->is_required,
                        'min_options' => $group->min_options,
                        'max_options' => $group->max_options,
                        'addons' => $group->addons->map(fn ($addon): array => [
                            'id' => $addon->id,
                            'name' => $addon->name,
                            'price_cents' => $addon->price_cents,
                            'price_formatted' => $addon->price()->format(),
                        ])->values(),
                    ])->values(),
                ])->values(),
            ])->values(),
        ];
    }
}
