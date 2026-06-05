<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Loan extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'amount',
        'duration_months',
        'interest_rate',
        'monthly_installment',
        'remaining_balance',
        'status',
        'rejection_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'duration_months' => 'integer',
            'interest_rate' => 'decimal:4',
            'monthly_installment' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
        ];
    }
}
