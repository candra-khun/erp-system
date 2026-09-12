<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportDailySummary extends Model
{
    use HasFactory;

    protected $table = 'report_daily_summaries';

    protected $fillable = [
        'warehouse_id',
        'summary_date',
        'total_sales',
        'total_purchases',
        'total_returns',
        'transaction_count',
        'cash_in',
        'cash_out',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'total_sales' => 'decimal:2',
            'total_purchases' => 'decimal:2',
            'total_returns' => 'decimal:2',
            'cash_in' => 'decimal:2',
            'cash_out' => 'decimal:2',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
