<div>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Laporan Laba Rugi</h2>
            <p class="text-sm text-gray-500 mt-1">Income Statement per periode</p>
        </div>
        <button wire:click="exportPdf" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition shadow-sm" wire:loading.attr="disabled">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span wire:loading.remove wire:target="exportPdf">Export PDF</span>
            <span wire:loading wire:target="exportPdf">Memproses...</span>
        </button>
    </div>

    {{-- Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
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
            <div class="flex items-end">
                <p class="text-xs text-gray-500">Periode: {{ \Carbon\Carbon::parse($report['startDate'])->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($report['endDate'])->format('d M Y') }}</p>
            </div>
        </div>
    </div>

    {{-- Laporan Laba Rugi --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-800">Laporan Laba Rugi</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody>
                    {{-- Pendapatan --}}
                    <tr class="bg-blue-50">
                        <td class="px-6 py-3 font-bold text-gray-800" colspan="2">PENDAPATAN</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-6 py-3 pl-10 text-gray-600">Pendapatan Penjualan</td>
                        <td class="px-6 py-3 text-right font-medium text-gray-900">Rp {{ number_format($report['totalRevenue'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="bg-blue-50 border-b border-gray-200">
                        <td class="px-6 py-3 font-bold text-gray-800">Total Pendapatan</td>
                        <td class="px-6 py-3 text-right font-bold text-gray-900">Rp {{ number_format($report['totalRevenue'], 0, ',', '.') }}</td>
                    </tr>

                    {{-- HPP --}}
                    <tr class="bg-orange-50">
                        <td class="px-6 py-3 font-bold text-gray-800" colspan="2">HARGA POKOK PENJUALAN (HPP)</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-6 py-3 pl-10 text-gray-600">Harga Pokok Penjualan</td>
                        <td class="px-6 py-3 text-right font-medium text-red-600">(Rp {{ number_format($report['totalCogs'], 0, ',', '.') }})</td>
                    </tr>
                    <tr class="bg-orange-50 border-b border-gray-200">
                        <td class="px-6 py-3 font-bold text-gray-800">Total HPP</td>
                        <td class="px-6 py-3 text-right font-bold text-red-600">(Rp {{ number_format($report['totalCogs'], 0, ',', '.') }})</td>
                    </tr>

                    {{-- Laba Kotor --}}
                    <tr class="bg-green-50 border-b-2 border-gray-300">
                        <td class="px-6 py-4 font-bold text-lg text-gray-900">LABA KOTOR</td>
                        <td class="px-6 py-4 text-right font-bold text-lg {{ $report['grossProfit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            Rp {{ number_format($report['grossProfit'], 0, ',', '.') }}
                        </td>
                    </tr>

                    {{-- Beban-beban --}}
                    <tr class="bg-purple-50">
                        <td class="px-6 py-3 font-bold text-gray-800" colspan="2">BEBAN OPERASIONAL</td>
                    </tr>
                    @forelse($report['expenses'] as $expense)
                        <tr class="border-b border-gray-100">
                            <td class="px-6 py-3 pl-10 text-gray-600">{{ $expense['label'] }}</td>
                            <td class="px-6 py-3 text-right font-medium text-red-600">(Rp {{ number_format($expense['amount'], 0, ',', '.') }})</td>
                        </tr>
                    @empty
                        <tr class="border-b border-gray-100">
                            <td class="px-6 py-3 pl-10 text-gray-400 italic">Tidak ada beban operasional</td>
                            <td class="px-6 py-3 text-right font-medium text-gray-400">Rp 0</td>
                        </tr>
                    @endforelse
                    <tr class="bg-purple-50 border-b border-gray-200">
                        <td class="px-6 py-3 font-bold text-gray-800">Total Beban Operasional</td>
                        <td class="px-6 py-3 text-right font-bold text-red-600">(Rp {{ number_format($report['totalExpenses'], 0, ',', '.') }})</td>
                    </tr>

                    {{-- Laba Bersih --}}
                    <tr class="{{ $report['netProfit'] >= 0 ? 'bg-green-100' : 'bg-red-100' }}">
                        <td class="px-6 py-5 font-bold text-xl text-gray-900">LABA BERSIH</td>
                        <td class="px-6 py-5 text-right font-bold text-xl {{ $report['netProfit'] >= 0 ? 'text-green-800' : 'text-red-800' }}">
                            Rp {{ number_format($report['netProfit'], 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
