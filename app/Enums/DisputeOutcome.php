<?php

namespace App\Enums;

enum DisputeOutcome: string
{
    case COMPLETE = 'complete';
    case CANCEL_REFUND = 'cancel_refund';
    case CANCEL_NO_REFUND = 'cancel_no_refund';
    case REOPEN = 'reopen';

    public function label(): string
    {
        return match ($this) {
            self::COMPLETE => 'Chấp nhận và hoàn tất giao dịch',
            self::CANCEL_REFUND => 'Chấp nhận, hủy và hoàn tiền',
            self::CANCEL_NO_REFUND => 'Chấp nhận, hủy không hoàn tiền',
            self::REOPEN => 'Từ chối và đưa giao dịch về luồng xử lý',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
