<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Transfer Stok</h1>
            <p class="text-gray-500 text-sm mt-1">Kelola perpindahan stok antar gudang.</p>
        </div>
        <button wire:click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Buat Transfer
        </button>
    </div>

    @if($showForm)
    <div class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Buat Transfer Baru</h2>
            <form wire:submit="store" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gudang Asal</label>
                        <select wire:model="source_warehouse_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih Gudang Asal</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                        @error('source_warehouse_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gudang Tujuan</label>
                        <select wire:model="destination_warehouse_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih Gudang Tujuan</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                        @error('destination_warehouse_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <textarea wire:model="notes" rows="2" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
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
                            <select wire:model="items.{{ $index }}.unit_id" class="w-28 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" title="Satuan">
                                <option value="">Satuan</option>
                                @if(!empty($item['product_id']) && ($unitOptionsByProduct[$item['product_id']] ?? null))
                                    @foreach($unitOptionsByProduct[$item['product_id']] as $unitOption)
                                        <option value="{{ $unitOption->id }}">{{ $unitOption->symbol }}</option>
                                    @endforeach
                                @endif
                            </select>
                            <input type="number" wire:model="items.{{ $index }}.quantity" min="1" step="0.01" placeholder="Qty" class="w-24 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            @if(count($items) > 1)
                                <button type="button" wire:click="removeItem({{ $index }})" class="mt-1 text-red-500 hover:text-red-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nomor transfer..." class="border-gray-300 rounded-md shadow-sm text-sm w-64 focus:border-blue-500 focus:ring-blue-500">
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Transfer</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gudang Asal</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gudang Tujuan</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($transfers as $transfer)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-sm font-mono text-gray-700">{{ $transfer->transfer_number }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $transfer->sourceWarehouse->name ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $transfer->destinationWarehouse->name ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm">
                        @php
                            $colors = ['draft' => 'bg-gray-100 text-gray-800', 'pending_approval' => 'bg-yellow-100 text-yellow-800', 'approved' => 'bg-blue-100 text-blue-800', 'in_transit' => 'bg-indigo-100 text-indigo-800', 'received' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-red-100 text-red-800'];
                            $labels = ['draft' => 'Draft', 'pending_approval' => 'Menunggu Persetujuan', 'approved' => 'Disetujui (siap kirim)', 'in_transit' => 'Dalam Perjalanan', 'received' => 'Diterima', 'cancelled' => 'Dibatalkan'];
                        @endphp
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $colors[$transfer->status] ?? 'bg-gray-100 text-gray-800' }}">{{ $labels[$transfer->status] ?? $transfer->status }}</span>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $transfer->created_at?->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-right space-x-1">
                        @if(in_array($transfer->status, ['draft', 'pending_approval']))
                            <button wire:click="approve({{ $transfer->id }})" wire:confirm="Setujui transfer ini? Stok akan keluar dari gudang asal." class="text-green-600 hover:text-green-800 text-xs font-medium">Setujui</button>
                            <button wire:click="cancel({{ $transfer->id }})" wire:confirm="Batalkan transfer ini?" class="text-red-600 hover:text-red-800 text-xs font-medium">Batal</button>
                        @elseif(in_array($transfer->status, ['approved', 'in_transit']))
                            <button wire:click="openReceive({{ $transfer->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Terima</button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada data transfer ditemukan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-4 border-t border-gray-200">
            {{ $transfers->links() }}
        </div>
    </div>

    @if($showReceiveForm)
    <div class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Terima Transfer</h2>
            <form wire:submit="submitReceive" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah diterima per item</label>
                    <div class="space-y-2">
                        @foreach($receiveItems as $index => $ri)
                        @php($ti = \App\Models\StockTransferItem::with('product')->find($ri['stock_transfer_item_id']))
                        <div class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                            <span class="text-sm text-gray-700 flex-1">{{ $ti?->product?->name ?? 'Item #'.$ri['stock_transfer_item_id'] }}</span>
                            <input type="number" min="0" step="0.01" wire:model="receiveItems.{{ $index }}.quantity"
                                   class="w-28 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" wire:click="$set('showReceiveForm', false)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Terima Stok</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>