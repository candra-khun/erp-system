<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Daftar Stok</h1>
    </div>

    <div class="mb-4 flex flex-col sm:flex-row gap-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama produk..."
            class="w-full sm:w-80 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        <select wire:model.live="warehouseFilter"
            class="w-full sm:w-60 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Gudang</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Nama Produk</th>
                        <th class="px-4 py-3">Gudang</th>
                        <th class="px-4 py-3 text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($stocks as $index => $stock)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500">{{ $stocks->firstItem() + $index }}</td>
                            <td class="px-4 py-3 font-medium">{{ $stock->product?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $stock->warehouse?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $stock->quantity, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">Tidak ada data stok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t">
            {{ $stocks->links() }}
        </div>
    </div>
</div>
