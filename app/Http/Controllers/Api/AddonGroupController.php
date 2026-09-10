<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreAddonGroupRequest;
use App\Http\Resources\AddonGroupResource;
use App\Models\AddonGroup;
use App\Models\Establishment;
use App\Services\Plans\PlanGate;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddonGroupController extends Controller
{
    public function __construct(private readonly PlanGate $plans) {}

    public function index(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('view', $establishment);

        $groups = $establishment->addonGroups()
            ->with(['addons' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => AddonGroupResource::collection($groups)]);
    }

    public function store(StoreAddonGroupRequest $request, Establishment $establishment): JsonResponse
    {
        $this->plans->assertHasFeature($establishment, PlanFeature::Addons);

        $data = $request->validated();

        $group = DB::transaction(function () use ($data, $establishment): AddonGroup {
            $group = $establishment->addonGroups()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_required' => $data['is_required'] ?? false,
                'min_options' => $data['min_options'] ?? 0,
                'max_options' => $data['max_options'] ?? 1,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => ((int) $establishment->addonGroups()->max('sort_order')) + 1,
            ]);

            $this->syncAddons($group, $data['addons'] ?? []);

            return $group;
        });

        return response()->json([
            'data' => new AddonGroupResource($group->load('addons')),
            'message' => 'Grupo de adicionais criado.',
        ], 201);
    }

    public function update(StoreAddonGroupRequest $request, Establishment $establishment, AddonGroup $addonGroup): JsonResponse
    {
        $this->plans->assertHasFeature($establishment, PlanFeature::Addons);
        abort_unless($addonGroup->establishment_id === $establishment->id, 404);

        $data = $request->validated();

        DB::transaction(function () use ($data, $addonGroup): void {
            $addonGroup->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_required' => $data['is_required'] ?? false,
                'min_options' => $data['min_options'] ?? 0,
                'max_options' => $data['max_options'] ?? 1,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (array_key_exists('addons', $data)) {
                $addonGroup->addons()->delete();
                $this->syncAddons($addonGroup, $data['addons']);
            }
        });

        return response()->json([
            'data' => new AddonGroupResource($addonGroup->refresh()->load('addons')),
            'message' => 'Grupo atualizado.',
        ]);
    }

    public function destroy(Request $request, Establishment $establishment, AddonGroup $addonGroup): JsonResponse
    {
        $this->authorize('update', $establishment);
        abort_unless($addonGroup->establishment_id === $establishment->id, 404);

        $addonGroup->delete();

        return response()->json(['message' => 'Grupo removido.']);
    }

    private function syncAddons(AddonGroup $group, array $addons): void
    {
        foreach (array_values($addons) as $index => $addon) {
            $group->addons()->create([
                'name' => $addon['name'],
                'price_cents' => Money::fromDecimal($addon['price'])->cents,
                'is_active' => $addon['is_active'] ?? true,
                'sort_order' => $index,
            ]);
        }
    }
}
