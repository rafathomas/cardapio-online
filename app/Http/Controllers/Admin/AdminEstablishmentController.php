<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstablishmentResource;
use App\Models\Establishment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEstablishmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $establishments = Establishment::query()
            ->with(['user', 'subscription.plan', 'businessHours'])
            ->withCount(['products', 'categories'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim()->value().'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('slug', 'like', $term));
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('is_published', $request->string('status')->value() === 'published');
            })
            ->latest('id')
            ->paginate(perPage: min($request->integer('per_page', 25), 100));

        return response()->json([
            'data' => collect($establishments->items())->map(fn (Establishment $e) => [
                ...(new EstablishmentResource($e))->toArray($request),
                'owner' => ['id' => $e->user->id, 'name' => $e->user->name, 'email' => $e->user->email],
            ]),
            'meta' => [
                'current_page' => $establishments->currentPage(),
                'last_page' => $establishments->lastPage(),
                'total' => $establishments->total(),
            ],
        ]);
    }

    public function show(Request $request, Establishment $establishment): JsonResponse
    {
        $establishment->load(['user', 'subscription.plan', 'businessHours'])
            ->loadCount(['products', 'categories', 'orders']);

        return response()->json([
            'data' => new EstablishmentResource($establishment),
            'owner' => [
                'id' => $establishment->user->id,
                'name' => $establishment->user->name,
                'email' => $establishment->user->email,
            ],
            'orders_count' => $establishment->orders_count,
        ]);
    }
}
