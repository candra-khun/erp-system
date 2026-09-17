<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Stock Opname</h1>
            <p class="text-gray-500 text-sm mt-1">Pencatatan dan penyesuaian stok fisik per gudang.</p>
        </div>
        <button wire:click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Buat Opname
        </button>
    </div>

    @if($showForm)
    <div class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Buat Stock Opname Baru</h2>
            <form wire:submit="store" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gudang</label>
                    <select wire:model.live="warehouse_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Pilih Gudang</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <textarea wire:model="notes" rows="2" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                @if($warehouse_id)
                <div>
                    <label class="text-sm font-medium text-gray-700 mb-2 block">Item Stok Fisik</label>
                    <div class="max-h-64 overflow-y-auto border rounded-lg divide-y">
                        @forelse($items as $index => $item)
                        <div class="flex items-center gap-3 p-3">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ $item['product_name'] }}</p>
                                <p class="text-xs text-gray-500">SKU: {{ $item['sku'] }} | Stok Sistem: {{ number_format((float) $item['system_qty'], 0, ',', '.') }} {{ $item['unit_symbol'] }}</p>
                            </div>
                            <input type="hidden" wire:model="items.{{ $index }}.product_id">
                            <input type="number" wire:model="items.{{ $index }}.physical_qty" min="0" step="0.01" placeholder="Qty Fisik" class="w-32 border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        @empty
                        <p class="p-4 text-sm text-gray-400 text-center">Tidak ada stok di gudang ini.</p>
                        @endforelse
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
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nomor opname..." class="border-gray-300 rounded-md shadow-sm text-sm w-64 focus:border-blue-500 focus:ring-blue-500">
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Opname</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gudang</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal Mulai</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Selesai</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($opnames as $opname)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-sm font-mono text-gray-700">{{ $opname->opname_number }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $opname->warehouse->name ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm">
                        @php
                            $colors = ['draft' => 'bg-gray-100 text-gray-800', 'in_progress' => 'bg-yellow-100 text-yellow-800', 'completed' => 'bg-green-100 text-green-800', 'approved' => 'bg-blue-100 text-blue-800', 'cancelled' => 'bg-red-100 text-red-800'];
                            $labels = ['draft' => 'Draft', 'in_progress' => 'Berjalan', 'completed' => 'Selesai', 'approved' => 'Disetujui', 'cancelled' => 'Dibatalkan'];
                        @endphp
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $colors[$opname->status] ?? 'bg-gray-100 text-gray-800' }}">{{ $labels[$opname->status] ?? $opname->status }}</span>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $opname->started_at?->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $opname->completed_at?->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-right space-x-1">
                        @if($opname->status === 'draft')
                            <button wire:click="start({{ $opname->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Mulai</button>
                            <button wire:click="approve({{ $opname->id }})" wire:confirm="Setujui adjustment stok langsung tanpa sesi hitung ulang?" class="text-green-600 hover:text-green-800 text-xs font-medium">Setujui</button>
                        @elseif($opname->status === 'in_progress')
                            <button wire:click="complete({{ $opname->id }})" wire:confirm="Selesaikan opname ini?" class="text-green-600 hover:text-green-800 text-xs font-medium">Selesaikan</button>
                        @elseif($opname->status === 'completed')
                            <button wire:click="approve({{ $opname->id }})" wire:confirm="Setujui adjustment stok?" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Setujui</button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada data stock opname ditemukan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-4 border-t border-gray-200">
            {{ $opnames->links() }}
        </div>
    </div>
</div>
