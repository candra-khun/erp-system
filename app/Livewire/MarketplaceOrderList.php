<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\MarketplaceChannel;
use App\Models\MarketplaceOrder;
use App\Models\Product;
use App\Services\MarketplaceSyncService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('components.layouts.erp', ['title' => 'Order Marketplace'])]
class MarketplaceOrderList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    public string $status = '';

    public ?int $channelId = null;

    public ?int $convertId = null;

    /** Detail item untuk konversi menjadi SO: product_id|qty|price per baris. */
    public array $convertLines = [];

    protected function rules(): array
    {
        return [
            'convertLines' => ['required', 'array', 'min:1'],
            'convertLines.*.product_id' => ['required', 'integer'],
            'convertLines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'convertLines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<MarketplaceOrder>
     */
    #[Computed]
    public function orders()
    {
        return MarketplaceOrder::with(['channel.warehouse', 'salesOrder'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->channelId !== null, fn ($q) => $q->where('marketplace_channel_id', $this->channelId))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('channel_order_number', 'like', '%'.$this->search.'%')
                    ->orWhere('customer_name', 'like', '%'.$this->search.'%')
                    ->orWhere('tracking_number', 'like', '%'.$this->search.'%');
            }))
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * Channel untuk dropdown filter — hanya dalam scope user.
     */
    #[Computed]
    public function channelOptions()
    {
        $ids = $this->accessibleWarehouseIds();

        return MarketplaceChannel::when($ids !== null, fn ($q) => $q->whereIn('warehouse_id', $ids))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function openConvert(int $orderId): void
    {
        $order = $this->orders()->getCollection()->firstWhere('id', $orderId);

        if (! $order) {
            return;
        }

        $this->convertId = $orderId;
        $items = is_array($order->payload) ? ($order->payload['items'] ?? []) : [];

        // Prefill baris dari payload (bila adapter sudah sertakan mapping produk).
        $this->convertLines = array_values(array_map(static fn (array $item): array => [
            'product_id' => (int) ($item['product_id'] ?? 0),
            'quantity' => (float) ($item['quantity'] ?? 1),
            'unit_price' => (float) ($item['unit_price'] ?? 0),
        ], $items));

        if ($this->convertLines === []) {
            $this->convertLines = [['product_id' => 0, 'quantity' => 1, 'unit_price' => 0]];
        }
    }

    public function addConvertLine(): void
    {
        $this->convertLines[] = ['product_id' => 0, 'quantity' => 1, 'unit_price' => 0];
    }

    public function removeConvertLine(int $index): void
    {
        unset($this->convertLines[$index]);
        $this->convertLines = array_values($this->convertLines);
    }

    public function convert(MarketplaceSyncService $service): void
    {
        $validated = $this->validate();

        $order = MarketplaceOrder::with('channel')->findOrFail($this->convertId);

        try {
            $so = $service->convertToSalesOrder($order, ['items' => $validated['convertLines']], (int) auth()->id());
            session()->flash('success', 'Order dikonversi menjadi SO '.$so->so_number.'.');
            $this->convertId = null;
            $this->convertLines = [];
        } catch (HttpException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Daftar produk untuk dropdown konversi manual.
     */
    #[Computed]
    public function productOptions()
    {
        return Product::orderBy('name')->where('is_active', true)->get(['id', 'name', 'sku']);
    }

    public function render()
    {
        return view('livewire.marketplace-order-list', ['orders' => $this->orders, 'channelOptions' => $this->channelOptions, 'productOptions' => $this->productOptions]);
    }
}
