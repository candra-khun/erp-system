<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class MasterDataAuditObserver
{
    /**
     * Attributes considered sensitive for change tracking.
     *
     * @var list<string>
     */
    private const PRICE_FIELDS = ['purchase_price', 'selling_price'];

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        $changes = $model->getChanges();

        // Only audit rows where meaningful columns changed (skip timestamp-only updates)
        $meaningful = collect($changes)
            ->except(['updated_at'])
            ->all();

        if (empty($meaningful)) {
            return;
        }

        $oldValues = [];
        foreach (array_keys($meaningful) as $key) {
            $oldValues[$key] = $model->getOriginal($key);
        }

        /** @var Request|null $request */
        $request = App::make(Request::class);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $this->resolveAction($model, $meaningful),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $meaningful,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        /** @var Request|null $request */
        $request = App::make(Request::class);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'created',
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => null,
            'new_values' => $model->getAttributes(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Handle the Model "deleted" event (covers soft deletes).
     */
    public function deleted(Model $model): void
    {
        /** @var Request|null $request */
        $request = App::make(Request::class);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $model->isForceDeleting() ? 'force_deleted' : 'deleted',
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $model->getOriginal(),
            'new_values' => null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function resolveAction(Model $model, array $changes): string
    {
        if (collect($changes)->keys()->intersect(self::PRICE_FIELDS)->isNotEmpty()) {
            return 'price_changed';
        }

        return 'updated';
    }
}
