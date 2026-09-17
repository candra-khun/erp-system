<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\MarketplaceChannel;
use App\Models\Warehouse;
use App\Services\MarketplaceSyncService;
use App\Support\WarehouseAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('components.layouts.erp', ['title' => 'Channel Marketplace'])]
class MarketplaceChannelList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $channelId = null;

    public string $name = '';

    public string $platform = 'tokopedia';

    public string $shop_name = '';

    public ?int $warehouseId = null;

    public bool $sync_orders = true;

    public bool $sync_stock = true;

    public string $api_credential = '';

    public string $errorMessage = '';

    public bool $isEdit = false;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'platform' => 'required|in:tokopedia,shopee,lazada,bukalapak,tokotalk,other',
            'shop_name' => 'nullable|string|max:150',
            'warehouseId' => ['nullable', 'integer'],
            'sync_orders' => 'boolean',
            'sync_stock' => 'boolean',
            'api_credential' => 'nullable|string',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<MarketplaceChannel>
     */
    #[Computed]
    public function channels()
    {
        return MarketplaceChannel::with(['warehouse', 'orders'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('shop_name', 'like', '%'.$this->search.'%');
            }))
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Warehouse>
     */
    #[Computed]
    public function warehouseOptions()
    {
        return $this->accessibleWarehouseOptions();
    }

    public function openCreate(): void
    {
        $this->reset(['channelId', 'name', 'shop_name', 'api_credential', 'errorMessage']);
        $this->platform = 'tokopedia';
        $this->sync_orders = true;
        $this->sync_stock = true;
        $this->isEdit = false;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $warehouseId = $this->clampWarehouseId($validated['warehouseId']);

        $credential = trim($validated['api_credential'] ?? '');

        $data = [
            'name' => $validated['name'],
            'platform' => $validated['platform'],
            'shop_name' => $validated['shop_name'] ?: null,
            'warehouse_id' => $warehouseId,
            'sync_orders' => $validated['sync_orders'],
            'sync_stock' => $validated['sync_stock'],
            'api_credential' => $credential !== '' ? $credential : null,
        ];

        if ($this->isEdit) {
            $channel = $this->resolveAccessibleChannel((int) $this->channelId);
            $channel->update($data);
            session()->flash('success', 'Channel marketplace diperbarui.');
        } else {
            MarketplaceChannel::create($data);
            session()->flash('success', 'Channel marketplace ditambahkan.');
        }

        $this->showForm = false;
    }

    public function syncStock(int $channelId, MarketplaceSyncService $service): void
    {
        $channel = $this->resolveAccessibleChannel($channelId);

        try {
            $result = $service->pushStock($channel);
            session()->flash('success', "Sinkronisasi stok: {$result['synced']} sukses, {$result['failed']} gagal.");
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleStatus(int $channelId): void
    {
        $channel = $this->resolveAccessibleChannel($channelId);
        $channel->update(['status' => $channel->status === 'active' ? 'inactive' : 'active']);
        session()->flash('success', 'Status channel diperbarui.');
    }

    /**
     * @throws HttpException 403 bila channel milik cabang lain.
     */
    private function resolveAccessibleChannel(int $channelId): MarketplaceChannel
    {
        $channel = MarketplaceChannel::findOrFail($channelId);

        if ($channel->warehouse_id !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $channel->warehouse_id)) {
            abort(403, 'Anda tidak memiliki akses ke channel dari cabang ini.');
        }

        return $channel;
    }

    public function render()
    {
        return view('livewire.marketplace-channel-list', ['channels' => $this->channels, 'warehouseOptions' => $this->warehouseOptions]);
    }
}
