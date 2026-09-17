<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_reconciliation_id',
        'value_date',
        'description',
        'amount',
        'bank_reference',
        'matched_transaction_id',
        'is_matched',
    ];

    protected function casts(): array
    {
        return [
            'value_date' => 'date',
            'amount' => 'decimal:2',
            'is_matched' => 'boolean',
        ];
    }

    /** @return BelongsTo<BankReconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    /** @return BelongsTo<CashTransaction, $this> */
    public function matchedTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class, 'matched_transaction_id');
    }
}
