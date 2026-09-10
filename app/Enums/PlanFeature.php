<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Recursos controlados por plano. Novos planos podem combinar estes
 * recursos livremente sem alterar a estrutura do sistema.
 */
enum PlanFeature: string
{
    case RemoveBranding = 'remove_branding';
    case Statistics = 'statistics';
    case Customization = 'customization';
    case Addons = 'addons';
    case CoverImage = 'cover_image';

    public function label(): string
    {
        return match ($this) {
            self::RemoveBranding => 'Remover marca da plataforma',
            self::Statistics => 'Estatísticas',
            self::Customization => 'Personalização do cardápio',
            self::Addons => 'Adicionais',
            self::CoverImage => 'Foto de capa',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
