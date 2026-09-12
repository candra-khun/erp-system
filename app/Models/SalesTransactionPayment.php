<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTransactionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_transaction_id',
        'payment_method',
        'amount',
        'reference_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<SalesTransaction>
     */
    public function salesTransaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class);
    }
}
