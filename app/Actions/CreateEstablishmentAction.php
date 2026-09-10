<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\BusinessHour;
use App\Models\Establishment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Billing\SubscriptionManager;
use App\Services\Plans\PlanGate;
use App\Services\QrCodeService;
use App\Services\SlugGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Cria o estabelecimento com tudo que ele precisa para existir:
 * slug unico, horarios padrao, assinatura do plano gratuito e QR Code.
 */
class CreateEstablishmentAction
{
    public function __construct(
        private readonly SlugGenerator $slugs,
        private readonly PlanGate $plans,
        private readonly SubscriptionManager $subscriptions,
        private readonly QrCodeService $qrCodes,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $user, array $data): Establishment
    {
        $this->plans->assertCanCreateEstablishment($user);

        $establishment = DB::transaction(function () use ($user, $data): Establishment {
            $slug = $this->slugs->unique(
                $data['slug'] ?? $data['name'],
                fn (string $candidate) => Establishment::query()->withTrashed()->where('slug', $candidate),
                config('cardapio.reserved_slugs'),
            );

            $establishment = $user->establishments()->create([
                ...$data,
                'slug' => $slug,
            ]);

            $this->createDefaultBusinessHours($establishment);

            $this->subscriptions->startFreePlan($establishment, $this->plans->defaultPlan());

            return $establishment;
        });

        // Fora da transacao: escrita em disco nao deve prender o banco.
        $this->qrCodes->generate($establishment);

        $this->audit->log('establishment.created', $establishment, $user, $establishment, [
            'slug' => $establishment->slug,
            'segment' => $establishment->segment->value,
        ]);

        return $establishment->refresh();
    }

    /** Segunda a domingo, 18h as 23h, fechado as segundas — um ponto de partida editavel. */
    private function createDefaultBusinessHours(Establishment $establishment): void
    {
        $rows = [];

        foreach (range(0, 6) as $weekday) {
            $rows[] = [
                'establishment_id' => $establishment->id,
                'weekday' => $weekday,
                'is_closed' => $weekday === 1,
                'opens_at' => '18:00:00',
                'closes_at' => '23:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        BusinessHour::insert($rows);
    }
}
