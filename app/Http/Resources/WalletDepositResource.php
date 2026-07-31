<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class WalletDepositResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'amount' => $this->amount,
            'status' => $this->status, 'payment_method' => $this->payment_method,
            'metadata' => $this->metadata, 'proof_image_url' => $this->proof_image_url,
            'external_reference' => $this->external_reference, 'note' => $this->note,
            'review_note' => $this->review_note, 'occurred_at' => $this->occurred_at,
            'submitted_at' => $this->submitted_at, 'confirmed_at' => $this->confirmed_at,
        ];
    }
}
