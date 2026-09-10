<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstablishmentResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim()->value().'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->boolean('blocked'), fn ($q) => $q->whereNotNull('blocked_at'))
            ->withCount('establishments')
            ->latest('id')
            ->paginate(perPage: min($request->integer('per_page', 25), 100));

        return response()->json([
            'data' => collect($users->items())->map(fn (User $user) => [
                ...(new UserResource($user))->toArray($request),
                'blocked' => $user->isBlocked(),
                'blocked_at' => $user->blocked_at?->toIso8601String(),
                'establishments_count' => $user->establishments_count,
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $user->load(['establishments.subscription.plan'])->loadCount('establishments');

        return response()->json([
            'data' => [
                ...(new UserResource($user))->toArray($request),
                'blocked' => $user->isBlocked(),
                'blocked_at' => $user->blocked_at?->toIso8601String(),
            ],
            'establishments' => EstablishmentResource::collection($user->establishments),
        ]);
    }

    public function block(Request $request, User $user): JsonResponse
    {
        // Um administrador nao pode bloquear a si mesmo nem outro administrador.
        if ($user->id === $request->user()->id || $user->isAdmin()) {
            return response()->json(['message' => 'Não é possível bloquear este usuário.'], 422);
        }

        // blocked_at fica fora de $fillable de proposito: so o admin altera, explicitamente.
        $user->forceFill(['blocked_at' => now()])->save();

        $this->audit->log('admin.user_blocked', $user, $request->user(), meta: ['target_user_id' => $user->id]);

        return response()->json(['message' => 'Usuário bloqueado.']);
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        $user->forceFill(['blocked_at' => null])->save();

        $this->audit->log('admin.user_unblocked', $user, $request->user(), meta: ['target_user_id' => $user->id]);

        return response()->json(['message' => 'Usuário desbloqueado.']);
    }
}
