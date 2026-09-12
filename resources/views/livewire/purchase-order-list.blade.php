<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Purchase Order</h1>
            <p class="text-gray-500 text-sm mt-1">Kelola pengadaan barang dari supplier.</p>
        </div>
        <button wire:click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Buat PO
        </button>
    </div>

    @if($showForm)
    <div class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Buat Purchase Order Baru</h2>
            <form wire:submit="store" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supplier <span class="text-red-500">*</span></label>
                        <select wire:model="supplier_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih Supplier</option>
                            @foreach($suppliers as $sup)
                                <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gudang Tujuan <span class="text-red-500">*</span></label>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimasi Tiba</label>
                        <input type="date" wire:model="expected_delivery_date" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('expected_delivery_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-sm font-medium text-gray-700">Item Produk</label>
                        <button type="button" wire:click="addItem" class="text-sm text-blue-600 hover:text-blue-800">+ Tambah Item</button>
                    </div>
                    <div class="space-y-2">
                        @foreach($items as $index => $item)
                        <div class="flex gap-2 items-start">
                            <select wire:model="items.{{ $index }}.product_id" class="flex-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Produk</option>
                                @foreach($products as $prod)
                                    <option value="{{ $prod->id }}">{{ $prod->sku }} - {{ $prod->name }}</option>
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
                    @error('items') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    @error('items.*.product_id') @error('items.*.quantity') @error('items.*.unit_price') <p class="text-red-500 text-xs mt-1">Periksa kembali item produk.</p> @enderror @enderror @enderror
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

    @if($showReceiveForm)
    <div class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-1">Terima Barang dari PO</h2>
            <p class="text-xs text-gray-500 mb-4">Masukkan jumlah yang benar-benar diterima. Penerimaan parsial diperbolehkan.</p>
            <form wire:submit="submitReceive" class="space-y-4">
                <div class="space-y-2">
                    @foreach($receiveItems as $index => $ri)
                    <div class="flex items-center justify-between gap-3 bg-gray-50 rounded-lg px-3 py-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $ri['product_name'] }}</p>
                            <p class="text-xs text-gray-500">Dipesan: {{ $ri['ordered'] }} · Sudah diterima: {{ $ri['received'] }}</p>
                        </div>
                        <input type="number" min="0" max="{{ $ri['ordered'] - $ri['received'] }}" step="0.01"
                               wire:model="receiveItems.{{ $index }}.quantity"
                               class="w-24 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    @endforeach
                </div>
                @error('receiveItems') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" wire:click="$set('showReceiveForm', false)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Terima Barang</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex flex-col sm:flex-row gap-3">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari PO Number..." class="border-gray-300 rounded-md shadow-sm text-sm w-full sm:w-80 focus:border-blue-500 focus:ring-blue-500">
            <select wire:model.live="statusFilter" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Semua Status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO Number</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($purchaseOrders as $po)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-sm font-mono text-gray-700">{{ $po->po_number }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $po->supplier->name ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $po->order_date?->format('d/m/Y') ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm">
                        @php
                            $statusColors = [
                                'draft' => 'bg-gray-100 text-gray-800',
                                'submitted' => 'bg-yellow-100 text-yellow-800',
                                'approved' => 'bg-blue-100 text-blue-800',
                                'sent_to_supplier' => 'bg-indigo-100 text-indigo-800',
                                'partial_received' => 'bg-amber-100 text-amber-800',
                                'received' => 'bg-green-100 text-green-800',
                                'completed' => 'bg-green-200 text-green-900',
                                'cancelled' => 'bg-red-100 text-red-800',
                            ];
                            $statusLabel = \App\Enums\PurchaseOrderStatus::tryFrom($po->status)?->label() ?? $po->status;
                        @endphp
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $statusColors[$po->status] ?? 'bg-gray-100 text-gray-800' }}">{{ $statusLabel }}</span>
                    </td>
                    <td class="px-5 py-3 text-sm text-right text-gray-900 font-medium">Rp {{ number_format((float) $po->total_amount, 0, ',', '.') }}</td>
                    <td class="px-5 py-3 text-sm text-right space-x-1 whitespace-nowrap">
                        @if(in_array($po->status, ['draft', 'submitted']))
                            <button wire:click="approve({{ $po->id }})" wire:confirm="Setujui PO ini?" class="text-green-600 hover:text-green-800 text-xs font-medium">Setujui</button>
                            <button wire:click="delete({{ $po->id }})" wire:confirm="Hapus PO ini?" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
                        @elseif($po->status === 'approved')
                            <button wire:click="sendToSupplier({{ $po->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Kirim ke Supplier</button>
                        @endif
                        @if(in_array($po->status, ['approved', 'sent_to_supplier', 'partial_received']))
                            <button wire:click="openReceive({{ $po->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Terima Barang</button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada data PO ditemukan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-200">
            {{ $purchaseOrders->links() }}
        </div>
    </div>
</div>
