<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'amount' => (float) $this->amount,
            'duration_months' => (int) $this->duration_months,
            'interest_rate' => (float) $this->interest_rate,
            'monthly_installment' => (float) $this->monthly_installment,
            'remaining_balance' => (float) $this->remaining_balance,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'receipt_number' => $this->receipt_number,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
