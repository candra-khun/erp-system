<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Sales Order</h1>
            <p class="text-gray-500 text-sm mt-1">Order penjualan non-POS (B2B/reseller) dengan invoice.</p>
        </div>
        <button wire:click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Buat SO
        </button>
    </div>

    @if($showForm)
    <div class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Buat Sales Order Baru</h2>
            <form wire:submit="store" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pelanggan <span class="text-red-500">*</span></label>
                        <select wire:model="customer_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih Pelanggan</option>
                            @foreach($customers as $cust)
                                <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                            @endforeach
                        </select>
                        @error('customer_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gudang Pengiriman <span class="text-red-500">*</span></label>
                        <select wire:model="warehouse_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih Gudang</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouse_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Order <span class="text-red-500">*</span></label>
                        <input type="date" wire:model="order_date" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('order_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Kirim</label>
                        <input type="date" wire:model="delivery_date" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-sm font-medium text-gray-700">Item Produk</label>
                        <button type="button" wire:click="addItem" class="text-sm text-blue-600 hover:text-blue-800">+ Tambah Item</button>
                    </div>
                    <div class="space-y-2">
                        @foreach($items as $index => $item)
                        @php
                            $itemProductUnits = [];
                            if (! empty($item['product_id'])) {
                                $baseUnitId = $products->first(fn ($p) => (int) $p->id === (int) $item['product_id'])?->base_unit_id;
                                if ($baseUnitId) {
                                    $itemProductUnits = $productUnits->filter(
                                        fn ($u) => (int) $u->id === (int) $baseUnitId || (int) ($u->base_unit_id ?? 0) === (int) $baseUnitId
                                    )->values();
                                }
                            }
                        @endphp
                        <div class="flex gap-2 items-start">
                            <select wire:model="items.{{ $index }}.product_id" class="flex-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Produk</option>
                                @foreach($products as $prod)
                                    <option value="{{ $prod->id }}">{{ $prod->sku }} - {{ $prod->name }}</option>
                                @endforeach
                            </select>
                            <select wire:model="items.{{ $index }}.unit_id" class="w-24 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" title="Satuan">
                                <option value="">Pcs</option>
                                @foreach($itemProductUnits as $unitOption)
                                    <option value="{{ $unitOption->id }}">{{ $unitOption->symbol }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="0.01" step="0.01" wire:model="items.{{ $index }}.quantity" placeholder="Qty" class="w-24 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_price" placeholder="Harga" class="w-32 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            @if(count($items) > 1)
                                <button type="button" wire:click="removeItem({{ $index }})" class="mt-1 text-red-500 hover:text-red-700">&times;</button>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400">Harga diisi per satuan yang dipilih — otomatis dikonversi ke satuan dasar saat disimpan.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <textarea wire:model="notes" rows="2" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Opsional"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Simpan Draft</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="mb-4 flex flex-col sm:flex-row gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari SO Number..." class="w-full sm:w-80 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-4 py-2 border">
        <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-4 py-2 border">
            <option value="">Semua Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SO Number</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($salesOrders as $so)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $so->so_number }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $so->customer?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $so->order_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php
                                $badgeColors = [
                                    'draft' => 'bg-gray-100 text-gray-800',
                                    'confirmed' => 'bg-blue-100 text-blue-800',
                                    'processing' => 'bg-yellow-100 text-yellow-800',
                                    'shipped' => 'bg-purple-100 text-purple-800',
                                    'delivered' => 'bg-green-100 text-green-800',
                                    'completed' => 'bg-emerald-100 text-emerald-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                ];
                                $colorClass = $badgeColors[$so->status] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colorClass }}">
                                {{ \App\Enums\SalesOrderStatus::tryFrom($so->status)?->label() ?? $so->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 font-medium">Rp {{ number_format((float) $so->total_amount, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                            @if($so->status === 'draft')
                                <button wire:click="confirm({{ $so->id }})" wire:confirm="Konfirmasi SO ini? Stok akan berkurang." class="text-green-600 hover:text-green-800 font-medium">Konfirmasi</button>
                                <button wire:click="delete({{ $so->id }})" wire:confirm="Yakin ingin menghapus?" class="text-red-600 hover:text-red-900">Hapus</button>
                            @elseif(in_array($so->status, ['confirmed', 'processing', 'shipped', 'delivered']))
                                <button wire:click="cancel({{ $so->id }})" wire:confirm="Batalkan SO ini? Stok yang sudah dikurangi akan dikembalikan." class="text-red-600 hover:text-red-900">Batalkan</button>
                            @endif
                            <button wire:click="printInvoice({{ $so->id }})" class="text-blue-600 hover:text-blue-800 font-medium">Invoice</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">Tidak ada data sales order.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $salesOrders->links() }}
    </div>
</div>
