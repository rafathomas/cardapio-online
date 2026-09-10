<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ReorderRequest;
use App\Http\Requests\Dashboard\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Establishment;
use App\Services\AuditLogger;
use App\Services\Plans\PlanGate;
use App\Services\SlugGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function __construct(
        private readonly PlanGate $plans,
        private readonly SlugGenerator $slugs,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('view', $establishment);

        $categories = $establishment->categories()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => CategoryResource::collection($categories)]);
    }

    public function store(StoreCategoryRequest $request, Establishment $establishment): JsonResponse
    {
        $this->plans->assertCanCreateCategory($establishment);

        $data = $request->validated();

        $category = $establishment->categories()->create([
            ...$data,
            'slug' => $this->slugs->unique(
                $data['name'],
                fn (string $candidate) => $establishment->categories()->where('slug', $candidate),
            ),
            'sort_order' => $data['sort_order'] ?? (((int) $establishment->categories()->max('sort_order')) + 1),
        ]);

        $this->audit->log('category.created', $category, $request->user(), $establishment);

        return response()->json([
            'data' => new CategoryResource($category->loadCount('products')),
            'message' => 'Categoria criada.',
        ], 201);
    }

    public function show(Request $request, Establishment $establishment, Category $category): JsonResponse
    {
        $this->authorizeCategory($establishment, $category);

        return response()->json([
            'data' => new CategoryResource($category->loadCount('products')),
        ]);
    }

    public function update(StoreCategoryRequest $request, Establishment $establishment, Category $category): JsonResponse
    {
        $this->authorizeCategory($establishment, $category);

        $data = $request->validated();

        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->slugs->unique(
                $data['name'],
                fn (string $candidate) => $establishment->categories()->where('slug', $candidate),
                ignoreId: $category->id,
            );
        }

        $category->update($data);

        $this->audit->log('category.updated', $category, $request->user(), $establishment);

        return response()->json([
            'data' => new CategoryResource($category->refresh()->loadCount('products')),
            'message' => 'Categoria atualizada.',
        ]);
    }

    public function destroy(Request $request, Establishment $establishment, Category $category): JsonResponse
    {
        $this->authorizeCategory($establishment, $category);

        // Produtos nao sao apagados junto: ficam sem categoria (nullOnDelete).
        $category->delete();

        $this->audit->log('category.deleted', $category, $request->user(), $establishment);

        return response()->json(['message' => 'Categoria removida.']);
    }

    public function reorder(ReorderRequest $request, Establishment $establishment): JsonResponse
    {
        $ids = $request->validated('ids');

        // Restringe aos ids que realmente pertencem ao estabelecimento.
        $owned = $establishment->categories()->whereIn('id', $ids)->pluck('id')->all();

        DB::transaction(function () use ($ids, $owned, $establishment): void {
            foreach ($ids as $position => $id) {
                if (! in_array((int) $id, $owned, true)) {
                    continue;
                }

                $establishment->categories()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return response()->json(['message' => 'Ordem atualizada.']);
    }

    private function authorizeCategory(Establishment $establishment, Category $category): void
    {
        $this->authorize('update', $establishment);

        // Impede alcançar a categoria de outro estabelecimento pela URL.
        abort_unless($category->establishment_id === $establishment->id, 404);
    }
}
