<?php

namespace App\Models;

use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'journal_number',
        'journal_date',
        'type',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
        'is_posted',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'is_posted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphTo<Model>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<JournalEntryLine>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Validate that total debits equal total credits.
     */
    public function isBalanced(): bool
    {
        $debits = $this->lines()->where('type', 'debit')->sum('amount');
        $credits = $this->lines()->where('type', 'credit')->sum('amount');

        return abs((float) $debits - (float) $credits) < 0.01;
    }
}
