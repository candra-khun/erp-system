<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportProductSummary extends Model
{
    use HasFactory;

    protected $table = 'report_product_summaries';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'period_type',
        'period_start',
        'period_end',
        'qty_sold',
        'revenue',
        'qty_purchased',
        'purchase_cost',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'qty_sold' => 'decimal:2',
            'revenue' => 'decimal:2',
            'qty_purchased' => 'decimal:2',
            'purchase_cost' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
