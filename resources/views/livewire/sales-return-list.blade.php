<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Retur Penjualan</h1>
            <p class="text-gray-500 text-sm mt-1">Kelola pengembalian barang dari pelanggan.</p>
        </div>
        <button wire:click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Buat Retur
        </button>
    </div>

    @if($showForm)
    <div class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Buat Retur Penjualan</h2>
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sales Order</label>
                    <select wire:model.live="sales_order_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Pilih Sales Order</option>
                        @foreach($salesOrders as $so)
                            <option value="{{ $so->id }}">{{ $so->so_number }}</option>
                        @endforeach
                    </select>
                    @error('sales_order_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <textarea wire:model="notes" rows="2" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                @if(count($items) > 0)
                <div>
                    <label class="text-sm font-medium text-gray-700 mb-2 block">Item Retur</label>
                    <div class="border rounded-lg divide-y">
                        @foreach($items as $index => $item)
                        <div class="p-3 flex items-center gap-3">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ $item['product_name'] }}</p>
                                <p class="text-xs text-gray-500">SKU: {{ $item['sku'] }} | Qty Order: {{ number_format((float) $item['order_qty'], 0, ',', '.') }} | Harga Satuan: Rp {{ number_format((float) $item['unit_price'], 0, ',', '.') }}</p>
                            </div>
                            <input type="number" wire:model="items.{{ $index }}.return_quantity" min="0" step="0.01" placeholder="Qty Retur" class="w-28 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <input type="text" wire:model="items.{{ $index }}.reason" placeholder="Alasan" class="w-40 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

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
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nomor retur..." class="border-gray-300 rounded-md shadow-sm text-sm w-64 focus:border-blue-500 focus:ring-blue-500">
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Retur</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pelanggan</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">SO Referensi</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($returns as $ret)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-sm font-mono text-gray-700">{{ $ret->return_number }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $ret->customer->name ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $ret->returnable?->so_number ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm">
                        @php
                            $colors = ['draft' => 'bg-gray-100 text-gray-800', 'approved' => 'bg-green-100 text-green-800', 'processed' => 'bg-blue-100 text-blue-800', 'cancelled' => 'bg-red-100 text-red-800'];
                            $labels = ['draft' => 'Draft', 'approved' => 'Disetujui', 'processed' => 'Diproses', 'cancelled' => 'Dibatalkan'];
                        @endphp
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $colors[$ret->status] ?? 'bg-gray-100 text-gray-800' }}">{{ $labels[$ret->status] ?? $ret->status }}</span>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $ret->return_date?->format('d M Y') ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-right space-x-1">
                        @if($ret->status === 'draft')
                            <button wire:click="approve({{ $ret->id }})" wire:confirm="Setujui retur ini? Stok akan dikembalikan." class="text-green-600 hover:text-green-800 text-xs font-medium">Setujui</button>
                            <button wire:click="cancel({{ $ret->id }})" wire:confirm="Batalkan retur ini?" class="text-red-600 hover:text-red-800 text-xs font-medium">Batal</button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada data retur penjualan ditemukan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-4 border-t border-gray-200">
            {{ $returns->links() }}
        </div>
    </div>
</div>
