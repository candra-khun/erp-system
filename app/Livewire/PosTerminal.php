<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\PaymentMethod;
use App\Events\StockChanged;
use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\SalesTransactionPayment;
use App\Services\FinanceService;
use App\Services\LoyaltyService;
use App\Services\ProductPricingService;
use App\Services\TransactionAuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'POS / Kasir'])]
class PosTerminal extends Component
{
    /** @use InteractsWithWarehouseAccess<self> */
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    /** @var array<int, array{product_id: int, name: string, sku: string, base_price: float, price: float, qty: int, unit_id: int, unit_symbol: string, unit_factor: float}> */
    public array $cart = [];

    public ?int $customer_id = null;

    public string $warehouse_id = '';

    public string $payment_method = 'cash';

    public string $paid_amount = '';

    public string $split_cash_amount = '';

    public string $split_non_cash_amount = '';

    public string $split_non_cash_method = 'transfer';

    public string $discount_amount = '0';

    public string $notes = '';

    public ?int $lastTransactionId = null;

    public ?string $lastTransactionNumber = null;

    public ?string $lastChangeAmount = null;

    public function mount(): void
    {
        if ($this->warehouse_id === '') {
            // Gudang pertama yang berada dalam scope akses user (PRD: Admin Cabang = 1 cabang)
            $first = $this->accessibleWarehouseOptions()
                ->sortBy('id')
                ->first();

            $this->warehouse_id = $first ? (string) $first->id : '';
        }
    }

    public function updatedWarehouseId(): void
    {
        $this->resetPage();
        $this->refreshCartPricing();
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

    /**
     * Ganti satuan jual untuk satu baris keranjang (PRD 4.1 multi-unit).
     * Harga per satuan = harga per satuan dasar x faktor konversi.
     */
    public function updateUnit(int $index, int|string $unitId): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        $unit = ProductUnit::find((int) $unitId);

        if (! $unit) {
            return;
        }

        $factor = $this->unitFactor($unit);

        $this->cart[$index]['unit_id'] = $unit->id;
        $this->cart[$index]['unit_symbol'] = $unit->symbol;
        $this->cart[$index]['unit_factor'] = $factor;

        $this->refreshCartPricing($index);
    }

    public function removeFromCart(int $index): void
    {
        if (isset($this->cart[$index])) {
            array_splice($this->cart, $index, 1);
        }
    }

    /**
     * Ubah kuantitas baris keranjang.
     *
     * Nilai dari input DOM selalu string, termasuk string kosong saat user
     * menghapus isi field untuk mengetik ulang — nilai tidak numerik diabaikan
     * agar baris tidak hilang secara tak terduga. Angka 0 (atau kurang) menghapus
     * baris dari keranjang.
     */
    public function updateQty(int $index, mixed $qty): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        if (! is_numeric($qty)) {
            return;
        }

        $qty = (int) $qty;

        if ($qty <= 0) {
            $this->removeFromCart($index);

            return;
        }

        $this->cart[$index]['qty'] = $qty;

        $this->refreshCartPricing($index);
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

    public function getSplitChangeProperty(): float
    {
        $tendered = (float) ($this->split_cash_amount === '' ? 0 : $this->split_cash_amount)
            + (float) ($this->split_non_cash_amount === '' ? 0 : $this->split_non_cash_amount);

        return max(0, $tendered - $this->discountedTotal);
    }

    public function getSplitNonCashLabelProperty(): string
    {
        return PaymentMethod::tryFrom($this->split_non_cash_method)?->label() ?? '-';
    }

    public function checkout(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Keranjang kosong.');

            return;
        }

        if ($this->warehouse_id === '' || ! $this->accessibleWarehouseOptions()->contains('id', (int) $this->warehouse_id)) {
            session()->flash('error', 'Gudang/kasir wajib dipilih.');

            return;
        }

        $this->validateOnly('payment_method', [
            'payment_method' => ['required', 'in:cash,transfer,card,split'],
        ]);

        $warehouseId = (int) $this->warehouse_id;
        $discount = (float) $this->discount_amount;
        $total = $this->discountedTotal;

        try {
            $settlement = $this->resolveSettlement($total);
        } catch (\RuntimeException $e) {
            $this->addError('paid_amount', $e->getMessage());
            session()->flash('error', $e->getMessage());

            return;
        }

        $paid = $settlement['paid'];
        $change = $settlement['change'];
        $payments = $settlement['payments'];

        try {
            DB::transaction(function () use ($warehouseId, $paid, $change, $discount, $total, $payments): void {
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
                    'change_amount' => $change,
                    'status' => 'completed',
                    'notes' => $this->notes !== '' ? $this->notes : null,
                ]);

                foreach ($this->cart as $item) {
                    // Simpan jumlah dalam satuan dasar (root), harga per satuan dasar —
                    // konsisten dengan PO/GRN yang menyimpan unit_price per satuan dasar.
                    $baseQty = round($item['qty'] * $item['unit_factor'], 4);
                    $baseUnitPrice = $item['unit_factor'] > 0
                        ? round($item['price'] / $item['unit_factor'], 4)
                        : $item['price'];

                    SalesTransactionItem::create([
                        'sales_transaction_id' => $transaction->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $baseQty,
                        'unit_price' => $baseUnitPrice,
                        'subtotal' => $item['price'] * $item['qty'],
                    ]);

                    event(new StockChanged(
                        productId: $item['product_id'],
                        warehouseId: $warehouseId,
                        type: 'out',
                        quantity: $baseQty,
                        referenceType: 'sales_transaction',
                        referenceId: $transaction->id,
                        userId: Auth::id(),
                        notes: 'Penjualan POS '.$transactionNumber.' ('.$item['qty'].' '.$item['unit_symbol'].')',
                    ));
                }

                // Komposisi pembayaran (PRD 4.4): satu baris per metode settlement.
                foreach ($payments as $payment) {
                    SalesTransactionPayment::create([
                        'sales_transaction_id' => $transaction->id,
                        'payment_method' => $payment['method'],
                        'amount' => $payment['amount'],
                        'notes' => $this->payment_method === 'split' ? 'Pembayaran campuran' : null,
                    ]);
                }

                app(FinanceService::class)->recordPosSale($transaction, $payments, Auth::id());

                $this->lastTransactionId = $transaction->id;
                $this->lastTransactionNumber = $transactionNumber;
                $this->lastChangeAmount = number_format($change, 0, ',', '.');

                // Poin loyalitas member (PRD 4.6)
                if ($transaction->customer_id) {
                    app(LoyaltyService::class)->awardForSale(
                        (int) $transaction->customer_id,
                        $total,
                        'sales_transaction',
                        (int) $transaction->id,
                        Auth::id(),
                    );
                }

                // Audit trail transaksi POS (PRD §5)
                app(TransactionAuditLogger::class)->log(
                    $transaction,
                    'pos_sale',
                    null,
                    [
                        'transaction_number' => $transactionNumber,
                        'total_amount' => $total,
                        'payment_method' => $this->payment_method,
                        'payments' => $payments,
                        'items' => collect($this->cart)->map(fn ($item) => [
                            'product_id' => $item['product_id'],
                            'qty' => $item['qty'],
                            'unit' => $item['unit_symbol'],
                            'qty_base' => round($item['qty'] * $item['unit_factor'], 4),
                        ])->all(),
                    ],
                    Auth::id(),
                );
            });

            $this->cart = [];
            $this->customer_id = null;
            $this->paid_amount = '';
            $this->split_cash_amount = '';
            $this->split_non_cash_amount = '';
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

    /**
     * Resolve tendered amount, change and the per-method settlement breakdown.
     *
     * Settlement amounts are the *net* amounts reaching each account, so they
     * always sum to the transaction total (change is already deducted).
     *
     * @return array{paid: float, change: float, payments: array<int, array{method: string, amount: float}>}
     *
     * @throws \RuntimeException when the tendered amount is invalid
     */
    private function resolveSettlement(float $total): array
    {
        if ($this->payment_method === 'split') {
            $cash = (float) ($this->split_cash_amount === '' ? 0 : $this->split_cash_amount);
            $nonCash = (float) ($this->split_non_cash_amount === '' ? 0 : $this->split_non_cash_amount);
            $tendered = round($cash + $nonCash, 2);

            if (! in_array($this->split_non_cash_method, ['transfer', 'card'], true)) {
                throw new \RuntimeException('Metode pembayaran non-tunai tidak valid.');
            }

            if ($tendered + 0.001 < $total) {
                throw new \RuntimeException('Total pembayaran campuran kurang dari total belanja.');
            }

            $change = round(max(0, $tendered - $total), 2);

            if ($change > $cash + 0.001) {
                throw new \RuntimeException('Kembalian melebihi uang tunai yang diterima.');
            }

            $cashNet = round($cash - $change, 2);

            $payments = [];
            if ($cashNet > 0.001) {
                $payments[] = ['method' => 'cash', 'amount' => $cashNet];
            }
            if ($nonCash > 0.001) {
                $payments[] = ['method' => $this->split_non_cash_method, 'amount' => round($nonCash, 2)];
            }

            return ['paid' => $tendered, 'change' => $change, 'payments' => $payments];
        }

        if ($this->payment_method === 'cash') {
            $tendered = round((float) ($this->paid_amount === '' ? 0 : $this->paid_amount), 2);

            if ($tendered + 0.001 < $total) {
                throw new \RuntimeException('Uang bayar kurang dari total belanja.');
            }

            return [
                'paid' => $tendered,
                'change' => round(max(0, $tendered - $total), 2),
                'payments' => [['method' => 'cash', 'amount' => round($total, 2)]],
            ];
        }

        // transfer / card — pelanggan membayar tepat sebesar total belanja
        return [
            'paid' => round($total, 2),
            'change' => 0.0,
            'payments' => [['method' => $this->payment_method, 'amount' => round($total, 2)]],
        ];
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
            if ($item['product_id'] === $product->id && $item['unit_id'] === $product->base_unit_id) {
                $item['qty']++;
                unset($item);

                $this->refreshCartPricing();

                return;
            }
        }
        unset($item);

        $baseUnit = $product->baseUnit;

        $this->cart[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'base_price' => (float) $product->selling_price,
            'price' => (float) $product->selling_price,
            'qty' => 1,
            'unit_id' => $baseUnit?->id ?? 0,
            'unit_symbol' => $baseUnit?->symbol ?? 'pcs',
            'unit_factor' => 1.0,
        ];

        $this->refreshCartPricing();
    }

    /**
     * Hitung ulang harga satuan baris keranjang dari harga bertingkat (PRD 4.1)
     * berdasarkan kuantitas dalam satuan dasar saat ini.
     */
    private function refreshCartPricing(?int $index = null): void
    {
        if ($this->cart === []) {
            return;
        }

        $warehouseId = $this->warehouse_id !== '' ? (int) $this->warehouse_id : null;
        $pricing = app(ProductPricingService::class);
        $indices = $index === null ? array_keys($this->cart) : [$index];

        foreach ($indices as $i) {
            if (! isset($this->cart[$i])) {
                continue;
            }

            $product = Product::find($this->cart[$i]['product_id']);

            if (! $product) {
                continue;
            }

            $baseQty = $this->cart[$i]['qty'] * $this->cart[$i]['unit_factor'];
            $basePrice = $pricing->resolveUnitPrice($product, $warehouseId, $baseQty);

            $this->cart[$i]['base_price'] = $basePrice;
            $this->cart[$i]['price'] = round($basePrice * $this->cart[$i]['unit_factor'], 2);
        }
    }

    /**
     * Faktor konversi satuan terhadap satuan dasar produk (bukan is_base unit global).
     */
    private function unitFactor(ProductUnit $unit): float
    {
        return $unit->is_base ? 1.0 : max(0.0001, (float) $unit->conversion_factor);
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

        // Opsi satuan per produk untuk baris keranjang (PRD 4.1 multi-unit konversi)
        $unitOptionsByProduct = [];
        $cartProductIds = collect($this->cart)->pluck('product_id')->unique()->values()->all();

        if ($cartProductIds !== []) {
            $baseUnitIds = Product::whereIn('id', $cartProductIds)
                ->pluck('base_unit_id', 'id')
                ->map(fn ($v) => $v !== null ? (int) $v : null);

            $familyIds = $baseUnitIds->filter()->unique()->values()->all();

            if ($familyIds !== []) {
                $units = ProductUnit::where(function ($q) use ($familyIds): void {
                    $q->whereIn('id', $familyIds)->orWhereIn('base_unit_id', $familyIds);
                })
                    ->orderBy('name')
                    ->get(['id', 'name', 'symbol', 'base_unit_id', 'is_base', 'conversion_factor']);

                foreach ($baseUnitIds as $productId => $baseUnitId) {
                    if (! $baseUnitId) {
                        continue;
                    }

                    $unitOptionsByProduct[(int) $productId] = $units->filter(
                        fn (ProductUnit $u) => (int) $u->id === $baseUnitId
                            || (int) ($u->base_unit_id ?? 0) === $baseUnitId
                    )->values();
                }
            }
        }

        return view('livewire.pos-terminal', [
            'products' => $products,
            'warehouses' => $this->accessibleWarehouseOptions(),
            'customers' => Customer::orderBy('name')->limit(100)->get(['id', 'name', 'type']),
            'paymentMethods' => PaymentMethod::cases(),
            'unitOptionsByProduct' => $unitOptionsByProduct,
        ]);
    }
}
