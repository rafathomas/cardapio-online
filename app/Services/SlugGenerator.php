<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Geracao de slugs unicos e seguros para URLs publicas.
 */
class SlugGenerator
{
    /**
     * Gera um slug unico para a query informada.
     *
     * @param  \Closure(string): Builder  $query  fabrica de query que filtra pelo slug candidato
     */
    public function unique(string $source, \Closure $query, array $reserved = [], ?int $ignoreId = null): string
    {
        $base = $this->normalize($source);
        $candidate = $base;
        $suffix = 1;

        while ($this->isTaken($candidate, $query, $reserved, $ignoreId)) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        return $candidate;
    }

    /**
     * Normaliza um texto livre em slug. Sempre retorna algo utilizavel:
     * entradas sem caracteres latinos caem em um identificador aleatorio.
     */
    public function normalize(string $source): string
    {
        $slug = Str::slug($source);

        if ($slug === '') {
            $slug = 'estabelecimento-'.Str::lower(Str::random(6));
        }

        return Str::limit($slug, 80, '');
    }

    private function isTaken(string $candidate, \Closure $query, array $reserved, ?int $ignoreId): bool
    {
        if (in_array($candidate, $reserved, true)) {
            return true;
        }

        /** @var Builder $builder */
        $builder = $query($candidate);

        if ($ignoreId !== null) {
            $builder->whereKeyNot($ignoreId);
        }

        return $builder->exists();
    }
}
