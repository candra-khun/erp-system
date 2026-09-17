<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Audit trail untuk transaksi bisnis (PRD §5 NFR:
 * "Seluruh perubahan data master & transaksi tercatat (siapa, kapan, apa yang berubah)").
 *
 * Berbeda dari MasterDataAuditObserver (otomatis via Eloquent events),
 * logger ini dipanggil eksplisit oleh service pada transisi status transaksi:
 * receive, approve, submit, confirm, cancel, checkout POS, dsb.
 */
class TransactionAuditLogger
{
    /**
     * Catat aksi bisnis pada sebuah transaksi.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        Model $model,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
    ): AuditLog {
        /** @var Request|null $request */
        $request = App::make(Request::class);

        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Catat transisi status transaksi (old status -> new status).
     */
    public function logStatusChange(Model $model, string $from, string $to, ?int $userId = null, ?string $note = null): AuditLog
    {
        return $this->log(
            $model,
            'status_changed',
            ['status' => $from],
            array_merge(['status' => $to], $note !== null ? ['note' => $note] : []),
            $userId,
        );
    }
}
