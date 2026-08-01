<?php

namespace App\Enums;

enum ProductAvailabilityStatus: string
{
    case AVAILABLE = 'available';
    case HELD = 'held';
    case TRANSACTING = 'transacting';
    case RENTED = 'rented';
    case SOLD = 'sold';
    case SUSPENDED = 'suspended';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
