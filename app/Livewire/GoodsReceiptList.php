<?php

namespace App\Livewire;

use App\Models\GoodsReceipt;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp')]
class GoodsReceiptList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $gr = GoodsReceipt::findOrFail($id);
        $gr->delete();

        session()->flash('message', 'Goods Receipt berhasil dihapus.');
    }

    public function render(): View
    {
        $query = GoodsReceipt::with(['purchaseOrder', 'warehouse'])
            ->when($this->search, fn ($q) => $q->where('grn_number', 'like', "%{$this->search}%"))
            ->orderByDesc('receipt_date');

        return view('livewire.goods-receipt-list', [
            'goodsReceipts' => $query->paginate(15),
        ]);
    }
}
