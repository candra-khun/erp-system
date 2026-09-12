<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Laporan Perputaran Stok</h1>
            <p class="text-gray-500 text-sm mt-1">Analisis perputaran inventori per produk dalam periode tertentu.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <form wire:submit.prevent="applyFilter" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                <input type="date" wire:model.live="startDate" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                <input type="date" wire:model.live="endDate" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Gudang</label>
                <select wire:model.live="filterWarehouseId" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Semua Gudang</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                <select wire:model.live="filterCategoryId" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Produk</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stok Awal</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Masuk</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Keluar</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stok Akhir</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rata-rata</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Turnover</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($reportData as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono text-gray-700">{{ $row['sku'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-800">{{ $row['name'] }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ number_format($row['opening_stock'], 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-green-600">{{ number_format($row['total_in'], 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-red-600">{{ number_format($row['total_out'], 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ number_format($row['closing_stock'], 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ number_format($row['avg_stock'], 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold {{ $row['turnover'] > 0 ? 'text-blue-600' : 'text-gray-400' }}">
                        {{ number_format($row['turnover'], 2) }}x
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada data untuk periode ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
