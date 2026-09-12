<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Account>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * @return HasMany<Account>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * @return HasMany<JournalEntryLine>
     */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
