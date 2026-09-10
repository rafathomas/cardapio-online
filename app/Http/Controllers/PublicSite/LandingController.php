<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()->active()->orderBy('sort_order')->orderBy('price_cents')->get();

        return view('public.landing', ['plans' => $plans]);
    }
}
