<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\PaymentMethod;
use App\Events\StockChanged;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\SalesTransactionPayment;
use App\Models\Warehouse;
use App\Services\FinanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'POS / Kasir'])]
class PosTerminal extends Component
{
    use WithPagination;

    public string $search = '';

    /** @var array<int, array{product_id: int, name: string, sku: string, price: float, qty: int}> */
    public array $cart = [];

    public ?int $customer_id = null;

    public string $warehouse_id = '';

    public string $payment_method = 'cash';

    public string $paid_amount = '';

    public string $discount_amount = '0';

    public string $notes = '';

    public ?int $lastTransactionId = null;

    public ?string $lastTransactionNumber = null;

    public ?string $lastChangeAmount = null;

    public function mount(): void
    {
        if ($this->warehouse_id === '') {
            $this->warehouse_id = (string) Warehouse::orderBy('id')->value('id') ?? '';
        }
    }

    public function updatedWarehouseId(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Add product to cart by product id.
     */
    public function addToCart(int $productId): void
    {
        $product = Product::findOrFail($productId);

        $this->appendCart($product);
    }

    /**
     * Add product to cart by SKU or barcode (from scanner input).
     */
    public function scanAdd(): void
    {
        $term = trim($this->search);

        if ($term === '') {
            return;
        }

        $product = Product::where('is_active', true)
            ->where(function ($q) use ($term): void {
                $q->where('sku', $term)->orWhere('barcode', $term);
            })
            ->first();

        if (! $product) {
            $this->dispatch('notify', type: 'error', message: "Produk dengan kode/barcode '{$term}' tidak ditemukan.");

            return;
        }

        $this->appendCart($product);
        $this->search = '';
        $this->resetPage();
    }

    public function removeFromCart(int $index): void
    {
        if (isset($this->cart[$index])) {
            array_splice($this->cart, $index, 1);
        }
    }

    public function updateQty(int $index, int $qty): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        if ($qty <= 0) {
            $this->removeFromCart($index);

            return;
        }

        $this->cart[$index]['qty'] = $qty;
    }

    public function getTotalProperty(): float
    {
        $total = 0.0;
        foreach ($this->cart as $item) {
            $total += $item['price'] * $item['qty'];
        }

        return $total;
    }

    public function getDiscountedTotalProperty(): float
    {
        $discount = (float) $this->discount_amount;

        return max(0, $this->total - $discount);
    }

    public function getChangeProperty(): float
    {
        $paid = (float) ($this->paid_amount === '' ? 0 : $this->paid_amount);

        return max(0, $paid - $this->discountedTotal);
    }

    public function checkout(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Keranjang kosong.');

            return;
        }

        if ($this->warehouse_id === '' || ! Warehouse::whereKey((int) $this->warehouse_id)->exists()) {
            session()->flash('error', 'Gudang/kasir wajib dipilih.');

            return;
        }

        $this->validateOnly('payment_method', [
            'payment_method' => ['required', 'in:cash,transfer,card,split'],
        ]);

        $warehouseId = (int) $this->warehouse_id;
        $paid = (float) ($this->paid_amount === '' ? 0 : $this->paid_amount);
        $discount = (float) $this->discount_amount;
        $total = $this->discountedTotal;

        if (in_array($this->payment_method, ['cash', 'split'], true) && $paid < $total) {
            $this->addError('paid_amount', 'Uang bayar kurang dari total.');
            session()->flash('error', 'Uang bayar kurang dari total belanja.');

            return;
        }

        try {
            DB::transaction(function () use ($warehouseId, $paid, $discount, $total): void {
                $transactionNumber = $this->generateTransactionNumber();

                $transaction = SalesTransaction::create([
                    'transaction_number' => $transactionNumber,
                    'warehouse_id' => $warehouseId,
                    'customer_id' => $this->customer_id,
                    'cashier_id' => Auth::id(),
                    'payment_method' => $this->payment_method,
                    'subtotal' => $this->total,
                    'discount_amount' => $discount,
                    'tax_amount' => 0,
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'change_amount' => max(0, $paid - $total),
                    'status' => 'completed',
                    'notes' => $this->notes !== '' ? $this->notes : null,
                ]);

                foreach ($this->cart as $item) {
                    SalesTransactionItem::create([
                        'sales_transaction_id' => $transaction->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['qty'],
                        'unit_price' => $item['price'],
                        'subtotal' => $item['price'] * $item['qty'],
                    ]);

                    event(new StockChanged(
                        productId: $item['product_id'],
                        warehouseId: $warehouseId,
                        type: 'out',
                        quantity: (float) $item['qty'],
                        referenceType: 'sales_transaction',
                        referenceId: $transaction->id,
                        userId: Auth::id(),
                        notes: 'Penjualan POS '.$transactionNumber,
                    ));
                }

                // Payment record (PRD 4.4 metode pembayaran)
                SalesTransactionPayment::create([
                    'sales_transaction_id' => $transaction->id,
                    'payment_method' => in_array($this->payment_method, ['cash', 'transfer', 'card'], true)
                        ? $this->payment_method
                        : 'other',
                    'amount' => min($paid, $total),
                    'notes' => 'Uang bayar: '.number_format($paid, 0, ',', '.')
                        .' | Kembalian: '.number_format(max(0, $paid - $total), 0, ',', '.'),
                ]);

                // Kas masuk (kembalian tidak masuk kas)
                app(FinanceService::class)->recordPosSaleCash(
                    $transaction,
                    min($paid, $total),
                    Auth::id(),
                );

                $this->lastTransactionId = $transaction->id;
                $this->lastTransactionNumber = $transactionNumber;
                $this->lastChangeAmount = number_format(max(0, $paid - $total), 0, ',', '.');
            });

            $this->cart = [];
            $this->customer_id = null;
            $this->paid_amount = '';
            $this->discount_amount = '0';
            $this->notes = '';

            session()->flash('success', 'Transaksi berhasil disimpan.');
        } catch (\Throwable $e) {
            $this->addError('checkout', $e->getMessage());
            session()->flash('error', 'Transaksi gagal: '.$e->getMessage());
        }
    }

    public function printReceipt(): void
    {
        if ($this->lastTransactionId) {
            $this->redirectRoute('pdf.pos-receipt', ['id' => $this->lastTransactionId], navigate: false);
        }
    }

    private function generateTransactionNumber(): string
    {
        $prefix = 'POS-'.now()->format('Ymd').'-';

        $last = SalesTransaction::where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function appendCart(Product $product): void
    {
        foreach ($this->cart as &$item) {
            if ($item['product_id'] === $product->id) {
                $item['qty']++;

                return;
            }
        }
        unset($item);

        $this->cart[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => (float) $product->selling_price,
            'qty' => 1,
        ];
    }

    public function render(): View
    {
        $products = Product::where('is_active', true)
            ->when($this->search, function ($q): void {
                $term = trim((string) $this->search);
                $q->where(function ($q2) use ($term): void {
                    $q2->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%")
                        ->orWhere('barcode', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.pos-terminal', [
            'products' => $products,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'customers' => Customer::orderBy('name')->limit(100)->get(['id', 'name', 'type']),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
