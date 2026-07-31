<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class WalletLedgerEntryResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $before = $this->balance_bucket === 'held' ? $this->held_before : $this->available_before;
        $after = $this->balance_bucket === 'held' ? $this->held_after : $this->available_after;
        return [
            'id' => $this->id,
            'type' => $this->type,
            'direction' => $this->direction,
            'balance_bucket' => $this->balance_bucket,
            'amount' => $this->amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'status' => $this->status,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
