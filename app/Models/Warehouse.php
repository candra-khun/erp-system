<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'address',
        'city',
        'province',
        'phone',
        'manager_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Stock>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * @return HasMany<PosShift>
     */
    public function posShifts(): HasMany
    {
        return $this->hasMany(PosShift::class);
    }
}
