<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /** Catalogo publico de planos, usado na landing page e no upgrade. */
    public function index(): JsonResponse
    {
        $plans = Plan::query()->active()->orderBy('sort_order')->orderBy('price_cents')->get();

        return response()->json(['data' => PlanResource::collection($plans)]);
    }
}
