<div class="flex h-[calc(100vh-4rem)]">
    <div class="flex-1 p-4 overflow-y-auto">
        <div class="mb-4">
            <h1 class="text-2xl font-bold text-gray-800">POS Terminal</h1>
        </div>

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg">{{ session('error') }}</div>
        @endif

        <div class="mb-4 flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <label class="block text-xs font-semibold text-gray-500 mb-1">SCAN BARCODE / SKU (Enter untuk tambah)</label>
                <input type="text" wire:model.live.debounce.500ms="search" id="pos-scan-input"
                       placeholder="Scan barcode atau ketik nama/SKU..."
                       wire:keydown.enter="scanAdd"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="sm:w-56">
                <label class="block text-xs font-semibold text-gray-500 mb-1">Gudang / Toko</label>
                <select wire:model.live="warehouse_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($products as $product)
                <button wire:click="addToCart({{ $product->id }})"
                        class="bg-white rounded-lg shadow p-4 text-left hover:ring-2 hover:ring-blue-500 transition cursor-pointer">
                    <p class="font-semibold text-gray-800 truncate">{{ $product->name }}</p>
                    <p class="text-sm text-gray-500">{{ $product->sku }}</p>
                    <p class="mt-2 text-lg font-bold text-blue-600">Rp {{ number_format((float) $product->selling_price, 0, ',', '.') }}</p>
                </button>
            @endforeach
            @if($products->isEmpty())
                <p class="col-span-full text-center text-gray-500 py-8">Tidak ada produk ditemukan.</p>
            @endif
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>

    <div class="w-96 bg-white shadow-lg flex flex-col border-l border-gray-200">
        <div class="p-4 border-b flex items-center justify-between">
            <h2 class="text-xl font-bold text-gray-800">Keranjang</h2>
            <select wire:model.live="customer_id" class="text-xs border border-gray-300 rounded-md px-2 py-1">
                <option value="">Umum</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->type }})</option>
                @endforeach
            </select>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            @if(count($cart) === 0)
                <p class="text-center text-gray-400 mt-8">Keranjang kosong</p>
            @endif
            @foreach($cart as $index => $item)
                <div class="mb-3 border-b border-gray-100 pb-3">
                    <div class="flex justify-between items-start">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-800 text-sm truncate">{{ $item['name'] }}</p>
                            <p class="text-xs text-gray-400">{{ $item['sku'] }} · Rp {{ number_format($item['price'], 0, ',', '.') }}</p>
                        </div>
                        <button wire:click="removeFromCart({{ $index }})" class="text-red-400 hover:text-red-600 text-sm">&times;</button>
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <div class="flex items-center border border-gray-200 rounded-lg">
                            <button wire:click="updateQty({{ $index }}, {{ max(0, $item['qty'] - 1) }})" class="px-2 py-1 text-gray-600 hover:bg-gray-50 rounded-l-lg">&minus;</button>
                            <input type="number" min="1"
                                   wire:change="updateQty({{ $index }}, $event.target.value)"
                                   value="{{ $item['qty'] }}" class="w-12 text-center text-sm border-x border-gray-200 py-1">
                            <button wire:click="updateQty({{ $index }}, {{ $item['qty'] + 1 }})" class="px-2 py-1 text-gray-600 hover:bg-gray-50 rounded-r-lg">&plus;</button>
                        </div>
                        <span class="text-sm font-semibold text-gray-800">Rp {{ number_format($item['price'] * $item['qty'], 0, ',', '.') }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="p-4 border-t space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Subtotal</span>
                <span class="font-medium">Rp {{ number_format($this->total, 0, ',', '.') }}</span>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Diskon (Rp)</label>
                <input type="number" min="0" wire:model.live.debounce.300ms="discount_amount" value="{{ $discount_amount }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex justify-between text-base font-bold border-t border-dashed pt-2">
                <span>TOTAL</span>
                <span class="text-blue-600">Rp {{ number_format($this->discountedTotal, 0, ',', '.') }}</span>
            </div>

            <div class="grid grid-cols-2 gap-2">
                @foreach($paymentMethods as $pm)
                    <button type="button" wire:click="$set('payment_method', '{{ $pm->value }}')"
                            class="px-3 py-2 text-sm font-medium rounded-lg border transition {{ $payment_method === $pm->value ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                        {{ $pm->label() }}
                    </button>
                @endforeach
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Uang Bayar (Rp)</label>
                <input type="number" min="0" wire:model.live.debounce.300ms="paid_amount" value="{{ $paid_amount }}"
                       class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 {{ $errors->has('paid_amount') ? 'border-red-400' : 'border-gray-300' }}">
                @error('paid_amount') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Kembalian</span>
                <span class="font-semibold text-green-600">Rp {{ number_format($this->change, 0, ',', '.') }}</span>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Catatan</label>
                <input type="text" wire:model="notes" placeholder="Opsional"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex gap-2">
                <button wire:click="checkout" wire:loading.attr="disabled"
                        class="flex-1 px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition disabled:opacity-50">
                    Bayar Sekarang
                </button>
                @if($lastTransactionId)
                    <button wire:click="printReceipt"
                            class="px-4 py-3 bg-gray-800 text-white font-semibold rounded-lg hover:bg-gray-900 transition">
                        Struk
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('livewire:navigated', () => focusScanInput());
    document.addEventListener('DOMContentLoaded', () => focusScanInput());

    function focusScanInput() {
        const input = document.getElementById('pos-scan-input');
        if (input) {
            input.focus();
        }
    }
</script>
