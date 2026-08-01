<?php

namespace App\Enums;

enum OfferModeCode: string
{
    case SELL = 'sell';
    case RENT = 'rent';

    public function label(): string
    {
        return match ($this) {
            self::SELL => 'Bán',
            self::RENT => 'Cho thuê',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
