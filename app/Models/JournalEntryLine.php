<?php

namespace App\Models;

use Database\Factories\JournalEntryLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    /** @use HasFactory<JournalEntryLineFactory> */
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'type',
        'amount',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<JournalEntry>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<Account>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
