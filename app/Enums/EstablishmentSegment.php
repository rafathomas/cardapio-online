<?php

declare(strict_types=1);

namespace App\Enums;

enum EstablishmentSegment: string
{
    case Lanchonete = 'lanchonete';
    case Pizzaria = 'pizzaria';
    case Acaiteria = 'acaiteria';
    case Cafeteria = 'cafeteria';
    case Restaurante = 'restaurante';
    case Bar = 'bar';
    case Confeitaria = 'confeitaria';
    case Padaria = 'padaria';
    case Sorveteria = 'sorveteria';
    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::Lanchonete => 'Lanchonete',
            self::Pizzaria => 'Pizzaria',
            self::Acaiteria => 'Açaiteria',
            self::Cafeteria => 'Cafeteria',
            self::Restaurante => 'Restaurante',
            self::Bar => 'Bar',
            self::Confeitaria => 'Confeitaria',
            self::Padaria => 'Padaria',
            self::Sorveteria => 'Sorveteria',
            self::Outro => 'Outro',
        };
    }

    /** Categorias sugeridas no onboarding, por segmento. */
    public function suggestedCategories(): array
    {
        return match ($this) {
            self::Lanchonete => ['Hambúrgueres', 'Porções', 'Combos', 'Bebidas'],
            self::Pizzaria => ['Pizzas Salgadas', 'Pizzas Doces', 'Bordas', 'Bebidas'],
            self::Acaiteria => ['Açaí', 'Complementos', 'Vitaminas', 'Bebidas'],
            self::Cafeteria => ['Cafés', 'Doces', 'Salgados', 'Bebidas Geladas'],
            self::Restaurante => ['Entradas', 'Pratos Principais', 'Sobremesas', 'Bebidas'],
            self::Bar => ['Drinks', 'Cervejas', 'Porções', 'Doses'],
            self::Confeitaria => ['Bolos', 'Tortas', 'Doces', 'Bebidas'],
            self::Padaria => ['Pães', 'Salgados', 'Doces', 'Bebidas'],
            self::Sorveteria => ['Sorvetes', 'Milkshakes', 'Açaí', 'Coberturas'],
            self::Outro => ['Destaques', 'Bebidas'],
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
