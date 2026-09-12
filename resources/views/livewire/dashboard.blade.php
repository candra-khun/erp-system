<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
            <p class="text-gray-500 text-sm mt-1">Ringkasan aktivitas bisnis Anda hari ini.</p>
        </div>
        <div class="flex items-center gap-2">
            <select wire:model.live="warehouseId" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Semua Gudang/Cabang</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-emerald-500">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Penjualan Hari Ini</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">Rp {{ number_format((float) $stats['today_sales'], 0, ',', '.') }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ number_format((int) $stats['today_transactions']) }} transaksi</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-500">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Penjualan Bulan Ini</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">Rp {{ number_format((float) $stats['monthly_sales'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-500">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Hutang Belum Lunas (AP)</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">Rp {{ number_format((float) $stats['pending_ap'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-amber-500">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Piutang Belum Tertagih (AR)</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">Rp {{ number_format((float) $stats['pending_ar'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-600">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Kas Masuk Hari Ini</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">Rp {{ number_format((float) $stats['cash_in_today'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-orange-600">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Kas Keluar Hari Ini</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">Rp {{ number_format((float) $stats['cash_out_today'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-rose-500">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Stok di Bawah Reorder Point</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format((int) $stats['low_stock_count']) }} produk</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-indigo-500">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Produk</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format((int) ($stats['total_products'] ?? 0)) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-700">Produk Terlaris Bulan Ini</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty Terjual</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pendapatan</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($topProducts as $product)
                    <tr>
                        <td class="px-5 py-3 text-sm text-gray-900">{{ $product->name }}</td>
                        <td class="px-5 py-3 text-sm text-right text-gray-600">{{ number_format((float) $product->total_qty, 0, ',', '.') }}</td>
                        <td class="px-5 py-3 text-sm text-right font-medium text-gray-900">Rp {{ number_format((float) $product->total_revenue, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-5 py-8 text-center text-gray-400 text-sm">Belum ada penjualan bulan ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-700">Transaksi Penjualan Terakhir</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Transaksi</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pelanggan</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($recentSales as $sale)
                    <tr>
                        <td class="px-5 py-3 text-sm text-gray-900">{{ $sale->transaction_number ?? 'N/A' }}</td>
                        <td class="px-5 py-3 text-sm text-gray-600">{{ $sale->customer->name ?? 'Walk-in' }}</td>
                        <td class="px-5 py-3 text-sm font-medium text-gray-900">Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</td>
                        <td class="px-5 py-3 text-sm text-gray-500">{{ $sale->created_at->format('d M Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-5 py-8 text-center text-gray-400 text-sm">Belum ada transaksi penjualan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
