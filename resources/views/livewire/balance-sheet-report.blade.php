<div>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Neraca</h2>
            <p class="text-sm text-gray-500 mt-1">Balance Sheet per tanggal</p>
        </div>
        <button wire:click="exportPdf" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition shadow-sm" wire:loading.attr="disabled">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span wire:loading.remove wire:target="exportPdf">Export PDF</span>
            <span wire:loading wire:target="exportPdf">Memproses...</span>
        </button>
    </div>

    {{-- Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Per Tanggal</label>
                <input type="date" wire:model.live="asOfDate" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="flex items-end sm:col-span-2">
                <p class="text-xs text-gray-500">Posisi keuangan per {{ \Carbon\Carbon::parse($report['as_of'])->format('d M Y') }}</p>
            </div>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Total Aset</p>
            <p class="text-xl font-bold text-gray-900 mt-1">Rp {{ number_format($report['total_assets'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Total Kewajiban</p>
            <p class="text-xl font-bold text-gray-900 mt-1">Rp {{ number_format($report['total_liabilities'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Total Ekuitas</p>
            <p class="text-xl font-bold text-gray-900 mt-1">Rp {{ number_format($report['total_equity'], 0, ',', '.') }}</p>
        </div>
    </div>

    @php
        $isBalanced = abs($report['total_assets'] - $report['total_liabilities_and_equity']) < 0.01;
    @endphp

    @unless($isBalanced)
        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
            Neraca belum seimbang (selisih Rp {{ number_format(abs($report['total_assets'] - $report['total_liabilities_and_equity']), 2, ',', '.') }}). Periksa jurnal yang belum diposting.
        </div>
    @endunless

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- ASET --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-blue-50">
                <h3 class="text-lg font-semibold text-gray-800">ASET</h3>
            </div>
            <table class="w-full text-sm">
                <tbody>
                    @forelse($report['assets'] as $account)
                        <tr class="border-b border-gray-100">
                            <td class="px-6 py-3 text-gray-600">
                                <span class="font-mono text-xs text-gray-400 mr-2">{{ $account['code'] }}</span>
                                {{ $account['name'] }}
                            </td>
                            <td class="px-6 py-3 text-right font-medium text-gray-900">Rp {{ number_format($account['balance'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-6 py-6 text-center text-gray-400 italic">Belum ada akun aset bersaldo.</td>
                        </tr>
                    @endforelse
                    <tr class="bg-blue-50 border-t-2 border-gray-300">
                        <td class="px-6 py-4 font-bold text-gray-900">TOTAL ASET</td>
                        <td class="px-6 py-4 text-right font-bold text-gray-900">Rp {{ number_format($report['total_assets'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-6">
            {{-- KEWAJIBAN --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-orange-50">
                    <h3 class="text-lg font-semibold text-gray-800">KEWAJIBAN</h3>
                </div>
                <table class="w-full text-sm">
                    <tbody>
                        @forelse($report['liabilities'] as $account)
                            <tr class="border-b border-gray-100">
                                <td class="px-6 py-3 text-gray-600">
                                    <span class="font-mono text-xs text-gray-400 mr-2">{{ $account['code'] }}</span>
                                    {{ $account['name'] }}
                                </td>
                                <td class="px-6 py-3 text-right font-medium text-gray-900">Rp {{ number_format($account['balance'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-6 py-6 text-center text-gray-400 italic">Belum ada kewajiban bersaldo.</td>
                            </tr>
                        @endforelse
                        <tr class="bg-orange-50 border-t-2 border-gray-300">
                            <td class="px-6 py-4 font-bold text-gray-900">TOTAL KEWAJIBAN</td>
                            <td class="px-6 py-4 text-right font-bold text-gray-900">Rp {{ number_format($report['total_liabilities'], 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- EKUITAS --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-green-50">
                    <h3 class="text-lg font-semibold text-gray-800">EKUITAS</h3>
                </div>
                <table class="w-full text-sm">
                    <tbody>
                        @foreach($report['equity'] as $account)
                            <tr class="border-b border-gray-100">
                                <td class="px-6 py-3 text-gray-600">
                                    <span class="font-mono text-xs text-gray-400 mr-2">{{ $account['code'] }}</span>
                                    {{ $account['name'] }}
                                </td>
                                <td class="px-6 py-3 text-right font-medium text-gray-900">Rp {{ number_format($account['balance'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-b border-gray-100">
                            <td class="px-6 py-3 text-gray-600 italic">Laba Ditahan (periode berjalan)</td>
                            <td class="px-6 py-3 text-right font-medium {{ $report['retained_earnings'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">
                                Rp {{ number_format($report['retained_earnings'], 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr class="bg-green-50 border-t-2 border-gray-300">
                            <td class="px-6 py-4 font-bold text-gray-900">TOTAL EKUITAS</td>
                            <td class="px-6 py-4 text-right font-bold text-gray-900">Rp {{ number_format($report['total_equity'], 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <tbody>
                        <tr class="bg-gray-100 border-t-2 border-gray-400">
                            <td class="px-6 py-4 font-bold text-gray-900">TOTAL KEWAJIBAN + EKUITAS</td>
                            <td class="px-6 py-4 text-right font-bold text-gray-900">Rp {{ number_format($report['total_liabilities_and_equity'], 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>