<div>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Laporan Penjualan</h2>
            <p class="text-sm text-gray-500 mt-1">Rekap penjualan per produk, cabang/gudang, dan kasir</p>
        </div>
        <button wire:click="exportExcel" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition shadow-sm" wire:loading.attr="disabled">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
            <span wire:loading wire:target="exportExcel">Memproses...</span>
        </button>
    </div>

    {{-- Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                <input type="date" wire:model.live="startDate" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                <input type="date" wire:model.live="endDate" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Gudang (Opsional)</label>
                <select wire:model.live="warehouseId" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Semua Gudang</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Kelompokkan Berdasarkan</label>
                <div class="flex gap-2">
                    <button wire:click="$set('groupBy', 'product')" class="flex-1 px-3 py-2 text-sm font-medium rounded-lg border transition {{ $groupBy === 'product' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">Per Produk</button>
                    <button wire:click="$set('groupBy', 'warehouse')" class="flex-1 px-3 py-2 text-sm font-medium rounded-lg border transition {{ $groupBy === 'warehouse' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">Per Gudang</button>
                    <button wire:click="$set('groupBy', 'cashier')" class="flex-1 px-3 py-2 text-sm font-medium rounded-lg border transition {{ $groupBy === 'cashier' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">Per Kasir</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            @if($groupBy === 'product')
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Produk</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($data as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-gray-700">{{ $row->sku }}</td>
                        <td class="px-4 py-3 text-gray-800">{{ $row->name }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ number_format((float) $row->total_qty, 2) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-900">Rp {{ number_format((float) $row->total_revenue, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada data untuk periode ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            @elseif($groupBy === 'warehouse')
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gudang / Cabang</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah Transaksi</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($data as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-800">{{ $row->warehouse }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ number_format((int) $row->transaction_count) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-900">Rp {{ number_format((float) $row->total_revenue, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada data untuk periode ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kasir / Sales</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah Transaksi</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($data as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-800">{{ $row->cashier }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ number_format((int) $row->transaction_count) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-900">Rp {{ number_format((float) $row->total_revenue, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-gray-400 text-sm">Tidak ada data untuk periode ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>
