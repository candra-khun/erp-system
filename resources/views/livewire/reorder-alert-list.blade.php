<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Peringatan Stok Rendah</h1>
            <p class="text-gray-500 text-sm mt-1">Daftar produk yang stoknya berada di bawah batas reorder point.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Produk</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gudang</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stok Saat Ini</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Reorder Point</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Selisih</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($alerts as $alert)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-sm font-mono text-gray-700">{{ $alert['sku'] }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $alert['name'] }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $alert['warehouse'] }}</td>
                    <td class="px-5 py-3 text-sm text-right font-medium {{ $alert['is_critical'] ? 'text-red-600' : 'text-yellow-600' }}">{{ number_format($alert['current_stock'], 2) }}</td>
                    <td class="px-5 py-3 text-sm text-right text-gray-600">{{ number_format($alert['reorder_point'], 2) }}</td>
                    <td class="px-5 py-3 text-sm text-right text-red-500">-{{ number_format($alert['deficit'], 2) }}</td>
                    <td class="px-5 py-3 text-sm text-center">
                        @if($alert['is_critical'])
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Kritis</span>
                        @else
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Rendah</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada produk dengan stok rendah saat ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>