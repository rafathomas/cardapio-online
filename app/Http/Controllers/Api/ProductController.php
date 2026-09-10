<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ImportProductsRequest;
use App\Http\Requests\Dashboard\ReorderRequest;
use App\Http\Requests\Dashboard\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Establishment;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\ImageUploadService;
use App\Services\Plans\PlanGate;
use App\Services\ProductImportService;
use App\Services\SlugGenerator;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(
        private readonly PlanGate $plans,
        private readonly SlugGenerator $slugs,
        private readonly ImageUploadService $images,
        private readonly AuditLogger $audit,
        private readonly ProductImportService $import,
    ) {}

    public function index(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('view', $establishment);

        $products = $establishment->products()
            ->with(['category', 'addonGroups'])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim()->value().'%';
                $q->where('name', 'like', $term);
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('is_active', $request->string('status')->value() === 'active');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(perPage: min($request->integer('per_page', 25), 100))
            ->withQueryString();

        return response()->json([
            'data' => ProductResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'plan' => $this->plans->summary($establishment),
        ]);
    }

    public function store(StoreProductRequest $request, Establishment $establishment): JsonResponse
    {
        // Limite do plano checado no backend: esconder o botao no front nao basta.
        $this->plans->assertCanCreateProduct($establishment);

        $data = $request->validated();

        $product = DB::transaction(function () use ($request, $establishment, $data): Product {
            $product = $establishment->products()->create([
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'slug' => $this->slugs->unique(
                    $data['name'],
                    fn (string $candidate) => $establishment->products()->withTrashed()->where('slug', $candidate),
                ),
                'description' => $data['description'] ?? null,
                'price_cents' => Money::fromDecimal($data['price'])->cents,
                'promo_price_cents' => isset($data['promo_price']) && $data['promo_price'] !== null
                    ? Money::fromDecimal($data['promo_price'])->cents
                    : null,
                'is_active' => $data['is_active'] ?? true,
                'is_featured' => $data['is_featured'] ?? false,
                'sort_order' => $data['sort_order'] ?? (((int) $establishment->products()->max('sort_order')) + 1),
            ]);

            if ($request->hasFile('image')) {
                $product->update([
                    'image_path' => $this->images->storeProductImage($request->file('image'), $establishment->id),
                ]);
            }

            if (isset($data['addon_group_ids'])) {
                $product->addonGroups()->sync($data['addon_group_ids']);
            }

            return $product;
        });

        $this->audit->log('product.created', $product, $request->user(), $establishment);

        return response()->json([
            'data' => new ProductResource($product->load('category')),
            'message' => 'Produto criado.',
        ], 201);
    }

    public function show(Request $request, Establishment $establishment, Product $product): JsonResponse
    {
        $this->authorizeProduct($establishment, $product, 'view');

        return response()->json([
            'data' => new ProductResource($product->load(['category', 'addonGroups.addons'])),
        ]);
    }

    public function update(StoreProductRequest $request, Establishment $establishment, Product $product): JsonResponse
    {
        $this->authorizeProduct($establishment, $product);

        $data = $request->validated();

        $payload = [
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_cents' => Money::fromDecimal($data['price'])->cents,
            'promo_price_cents' => isset($data['promo_price']) && $data['promo_price'] !== null
                ? Money::fromDecimal($data['promo_price'])->cents
                : null,
        ];

        foreach (['is_active', 'is_featured', 'sort_order'] as $optional) {
            if (array_key_exists($optional, $data)) {
                $payload[$optional] = $data[$optional];
            }
        }

        if ($data['name'] !== $product->name) {
            $payload['slug'] = $this->slugs->unique(
                $data['name'],
                fn (string $candidate) => $establishment->products()->withTrashed()->where('slug', $candidate),
                ignoreId: $product->id,
            );
        }

        if ($request->hasFile('image')) {
            $this->images->delete($product->image_path);
            $payload['image_path'] = $this->images->storeProductImage($request->file('image'), $establishment->id);
        }

        $product->update($payload);

        if (isset($data['addon_group_ids'])) {
            $product->addonGroups()->sync($data['addon_group_ids']);
        }

        $this->audit->log('product.updated', $product, $request->user(), $establishment);

        return response()->json([
            'data' => new ProductResource($product->refresh()->load('category')),
            'message' => 'Produto atualizado.',
        ]);
    }

    public function destroy(Request $request, Establishment $establishment, Product $product): JsonResponse
    {
        $this->authorizeProduct($establishment, $product, 'delete');

        $product->delete();

        $this->audit->log('product.deleted', $product, $request->user(), $establishment);

        return response()->json(['message' => 'Produto removido.']);
    }

    /** Duplicar respeita o limite do plano como se fosse um produto novo. */
    public function duplicate(Request $request, Establishment $establishment, Product $product): JsonResponse
    {
        $this->authorizeProduct($establishment, $product);
        $this->plans->assertCanCreateProduct($establishment);

        $copy = $product->replicate(['slug', 'created_at', 'updated_at']);
        $copy->name = mb_substr($product->name.' (cópia)', 0, 120);
        $copy->slug = $this->slugs->unique(
            $copy->name,
            fn (string $candidate) => $establishment->products()->withTrashed()->where('slug', $candidate),
        );
        $copy->is_active = false;
        $copy->sort_order = ((int) $establishment->products()->max('sort_order')) + 1;
        $copy->save();

        $copy->addonGroups()->sync($product->addonGroups()->pluck('addon_groups.id')->all());

        $this->audit->log('product.duplicated', $copy, $request->user(), $establishment, [
            'source_id' => $product->id,
        ]);

        return response()->json([
            'data' => new ProductResource($copy->load('category')),
            'message' => 'Produto duplicado.',
        ], 201);
    }

    public function toggle(Request $request, Establishment $establishment, Product $product): JsonResponse
    {
        $this->authorizeProduct($establishment, $product);

        $product->update(['is_active' => ! $product->is_active]);

        return response()->json([
            'data' => new ProductResource($product->refresh()->load('category')),
            'message' => $product->is_active ? 'Produto ativado.' : 'Produto desativado.',
        ]);
    }

    public function import(ImportProductsRequest $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('update', $establishment);

        $result = $this->import->import($establishment, $request->file('file'));

        if ($result['created'] > 0) {
            $this->audit->log('product.imported', $establishment, $request->user(), $establishment, [
                'created' => $result['created'],
                'errors' => count($result['errors']),
            ]);
        }

        return response()->json([
            'created' => $result['created'],
            'errors' => $result['errors'],
            'plan' => $this->plans->summary($establishment),
            'message' => $result['created'] > 0
                ? "{$result['created']} produto(s) importado(s)."
                : 'Nenhum produto importado.',
        ]);
    }

    public function reorder(ReorderRequest $request, Establishment $establishment): JsonResponse
    {
        $ids = $request->validated('ids');
        $owned = $establishment->products()->whereIn('id', $ids)->pluck('id')->all();

        DB::transaction(function () use ($ids, $owned, $establishment): void {
            foreach ($ids as $position => $id) {
                if (! in_array((int) $id, $owned, true)) {
                    continue;
                }

                $establishment->products()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return response()->json(['message' => 'Ordem atualizada.']);
    }

    private function authorizeProduct(Establishment $establishment, Product $product, string $ability = 'update'): void
    {
        $this->authorize($ability === 'view' ? 'view' : $ability, $establishment);

        abort_unless($product->establishment_id === $establishment->id, 404);
    }
}
