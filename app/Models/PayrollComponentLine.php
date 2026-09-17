<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayrollComponentType;
use Database\Factories\PayrollComponentLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detail komponen yang dikenakan ke payroll karyawan — PRD Fase 4 #12.
 */
class PayrollComponentLine extends Model
{
    /** @use HasFactory<PayrollComponentLineFactory> */
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'payroll_component_id',
        'name',
        'type',
        'amount',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'type' => PayrollComponentType::class,
        ];
    }

    /**
     * @return BelongsTo<Payroll, $this>
     */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    /**
     * @return BelongsTo<PayrollComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class);
    }
}
