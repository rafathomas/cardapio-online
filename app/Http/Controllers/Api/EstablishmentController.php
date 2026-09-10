<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateEstablishmentAction;
use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreEstablishmentRequest;
use App\Http\Requests\Dashboard\UpdateBusinessHoursRequest;
use App\Http\Requests\Dashboard\UpdateEstablishmentRequest;
use App\Http\Resources\BusinessHourResource;
use App\Http\Resources\EstablishmentResource;
use App\Models\Establishment;
use App\Services\AuditLogger;
use App\Services\ImageUploadService;
use App\Services\Plans\PlanGate;
use App\Services\QrCodeService;
use App\Services\SlugGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstablishmentController extends Controller
{
    public function __construct(
        private readonly PlanGate $plans,
        private readonly AuditLogger $audit,
        private readonly ImageUploadService $images,
        private readonly SlugGenerator $slugs,
        private readonly QrCodeService $qrCodes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $establishments = $request->user()
            ->establishments()
            ->with(['subscription.plan', 'businessHours'])
            ->withCount(['products', 'categories'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => EstablishmentResource::collection($establishments),
        ]);
    }

    public function store(StoreEstablishmentRequest $request, CreateEstablishmentAction $action): JsonResponse
    {
        $establishment = $action->execute($request->user(), $request->validated());

        $establishment->load(['subscription.plan', 'businessHours'])
            ->loadCount(['products', 'categories']);

        return response()->json([
            'data' => new EstablishmentResource($establishment),
            'message' => 'Estabelecimento criado.',
        ], 201);
    }

    public function show(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('view', $establishment);

        $establishment->load(['subscription.plan', 'businessHours'])
            ->loadCount(['products', 'categories']);

        return response()->json([
            'data' => new EstablishmentResource($establishment),
            'plan' => $this->plans->summary($establishment),
        ]);
    }

    public function update(UpdateEstablishmentRequest $request, Establishment $establishment): JsonResponse
    {
        $data = $request->safe()->except(['logo', 'cover', 'slug']);

        // Cores e capa fazem parte da personalizacao: recurso do plano PRO.
        if ($this->touchesCustomization($request, $establishment)) {
            $this->plans->assertHasFeature($establishment, PlanFeature::Customization);
        }

        if ($request->hasFile('cover')) {
            $this->plans->assertHasFeature($establishment, PlanFeature::CoverImage);
        }

        if ($request->filled('slug') && $request->input('slug') !== $establishment->slug) {
            $data['slug'] = $this->slugs->unique(
                (string) $request->input('slug'),
                fn (string $candidate) => Establishment::query()->withTrashed()->where('slug', $candidate),
                config('cardapio.reserved_slugs'),
                $establishment->id,
            );
        }

        if ($request->hasFile('logo')) {
            $this->images->delete($establishment->logo_path);
            $data['logo_path'] = $this->images->storeLogo($request->file('logo'), $establishment->id);
        }

        if ($request->hasFile('cover')) {
            $this->images->delete($establishment->cover_path);
            $data['cover_path'] = $this->images->storeCover($request->file('cover'), $establishment->id);
        }

        $slugChanged = isset($data['slug']);

        $establishment->update($data);

        // O QR Code aponta para o slug: mudou o slug, muda a imagem.
        if ($slugChanged) {
            $this->qrCodes->generate($establishment->refresh(), bumpVersion: true);
        }

        $this->audit->log('establishment.updated', $establishment, $request->user(), $establishment, [
            'fields' => array_keys($data),
        ]);

        $establishment->refresh()->load(['subscription.plan', 'businessHours'])
            ->loadCount(['products', 'categories']);

        return response()->json([
            'data' => new EstablishmentResource($establishment),
            'message' => 'Alterações salvas.',
        ]);
    }

    public function destroy(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('delete', $establishment);

        $establishment->delete();

        $this->audit->log('establishment.deleted', $establishment, $request->user(), $establishment);

        return response()->json(['message' => 'Estabelecimento removido.']);
    }

    /** Publica o cardapio. Exige e-mail verificado e ao menos um produto ativo. */
    public function publish(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('publish', $establishment);

        if (! $request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Confirme seu e-mail antes de publicar o cardápio.',
                'error_code' => 'email_unverified',
            ], 422);
        }

        if ($establishment->products()->where('is_active', true)->doesntExist()) {
            return response()->json([
                'message' => 'Cadastre ao menos um produto ativo antes de publicar.',
                'error_code' => 'no_active_products',
            ], 422);
        }

        $establishment->update([
            'is_published' => true,
            'published_at' => $establishment->published_at ?? now(),
        ]);

        $this->qrCodes->forEstablishment($establishment);

        $this->audit->log('establishment.published', $establishment, $request->user(), $establishment);

        return response()->json([
            'message' => 'Seu cardápio está no ar.',
            'public_url' => $establishment->publicUrl(),
        ]);
    }

    public function unpublish(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('publish', $establishment);

        $establishment->update(['is_published' => false]);

        $this->audit->log('establishment.unpublished', $establishment, $request->user(), $establishment);

        return response()->json(['message' => 'Cardápio despublicado.']);
    }

    public function businessHours(UpdateBusinessHoursRequest $request, Establishment $establishment): JsonResponse
    {
        DB::transaction(function () use ($request, $establishment): void {
            foreach ($request->validated('hours') as $hour) {
                $establishment->businessHours()->updateOrCreate(
                    ['weekday' => $hour['weekday']],
                    [
                        'is_closed' => $hour['is_closed'],
                        'opens_at' => $hour['is_closed'] ? null : $hour['opens_at'],
                        'closes_at' => $hour['is_closed'] ? null : $hour['closes_at'],
                    ],
                );
            }
        });

        return response()->json([
            'data' => BusinessHourResource::collection(
                $establishment->businessHours()->orderBy('weekday')->get()
            ),
            'message' => 'Horários atualizados.',
        ]);
    }

    private function touchesCustomization(UpdateEstablishmentRequest $request, Establishment $establishment): bool
    {
        foreach (['primary_color', 'secondary_color'] as $field) {
            if ($request->filled($field) && $request->input($field) !== $establishment->{$field}) {
                return true;
            }
        }

        return false;
    }
}
