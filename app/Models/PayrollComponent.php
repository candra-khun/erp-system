<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayrollComponentType;
use Database\Factories\PayrollComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Komponen gaji (tunjangan/potongan/pajak) — PRD Fase 4 #12.
 */
class PayrollComponent extends Model
{
    /** @use HasFactory<PayrollComponentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'calculation_type',
        'amount',
        'percentage',
        'is_taxable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PayrollComponentType::class,
            'amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PayrollComponentLine>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollComponentLine::class);
    }
}
