<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MenuView;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Contabiliza a visualizacao fora do request para nao pesar no carregamento
 * do cardapio. Agregado por dia: uma linha por estabelecimento/data.
 */
class RecordMenuView implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $establishmentId,
        public readonly string $date,
    ) {}

    public function handle(): void
    {
        /*
         * Um unico statement: insere com 1 ou incrementa a linha do dia.
         * Atomico, entao acessos simultaneos nao perdem contagem nem duplicam.
         */
        MenuView::query()->upsert(
            [[
                'establishment_id' => $this->establishmentId,
                'viewed_on' => $this->date,
                'views' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['establishment_id', 'viewed_on'],
            [
                'views' => DB::raw('views + 1'),
                'updated_at' => now(),
            ],
        );
    }
}
