<div>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Transaksi Kas</h2>
            <p class="text-sm text-gray-500 mt-1">Kelola pemasukan dan pengeluaran kas</p>
        </div>
        <button wire:click="openCreateModal" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Transaksi
        </button>
    </div>

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
            {{ session('message') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Cari</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Nomor / deskripsi..." class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tipe</label>
                <select wire:model.live="filterType" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Semua Tipe</option>
                    <option value="in">Kas Masuk</option>
                    <option value="out">Kas Keluar</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Dari</label>
                <input type="date" wire:model.live="dateFrom" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Sampai</label>
                <input type="date" wire:model.live="dateTo" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs uppercase text-gray-500 tracking-wider">
                    <tr>
                        <th class="px-4 py-3 font-semibold">No. Transaksi</th>
                        <th class="px-4 py-3 font-semibold">Tipe</th>
                        <th class="px-4 py-3 font-semibold">Kategori</th>
                        <th class="px-4 py-3 font-semibold text-right">Nominal</th>
                        <th class="px-4 py-3 font-semibold">Tanggal</th>
                        <th class="px-4 py-3 font-semibold">Deskripsi</th>
                        <th class="px-4 py-3 font-semibold">Gudang</th>
                        <th class="px-4 py-3 font-semibold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($transactions as $tx)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $tx->transaction_number }}</td>
                            <td class="px-4 py-3">
                                @if ($tx->type === 'in')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Masuk</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Keluar</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 capitalize">{{ $tx->category }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $tx->type === 'in' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $tx->type === 'in' ? '+' : '-' }}Rp {{ number_format((float) $tx->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $tx->transaction_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $tx->description ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $tx->warehouse?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="delete({{ $tx->id }})" wire:confirm="Yakin ingin menghapus transaksi ini?" class="text-red-500 hover:text-red-700 transition" title="Hapus">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-400">Tidak ada data transaksi kas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $transactions->links() }}
        </div>
    </div>

    {{-- Modal Create --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Tambah Transaksi Kas</h3>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="store" class="p-6 space-y-4">
                    {{-- Type --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Transaksi <span class="text-red-500">*</span></label>
                        <select wire:model="type" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 @error('type') border-red-500 @enderror">
                            <option value="in">Kas Masuk</option>
                            <option value="out">Kas Keluar</option>
                        </select>
                        @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Category --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                        <select wire:model="category" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 @error('category') border-red-500 @enderror">
                            <option value="sales">Penjualan</option>
                            <option value="purchase">Pembelian</option>
                            <option value="operational">Operasional</option>
                            <option value="salary">Gaji</option>
                            <option value="other">Lainnya</option>
                        </select>
                        @error('category') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Amount --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nominal <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0.01" wire:model="amount" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 @error('amount') border-red-500 @enderror" placeholder="0.00">
                        @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Transaction Date --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal <span class="text-red-500">*</span></label>
                        <input type="date" wire:model="transactionDate" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 @error('transactionDate') border-red-500 @enderror">
                        @error('transactionDate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                        <textarea wire:model="description" rows="3" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 @error('description') border-red-500 @enderror" placeholder="Keterangan transaksi..."></textarea>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Warehouse --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gudang</label>
                        <select wire:model="warehouseId" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 @error('warehouseId') border-red-500 @enderror">
                            <option value="">-- Pilih Gudang (Opsional) --</option>
                            @foreach ($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouseId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Payment Method --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Metode Pembayaran</label>
                        <input type="text" wire:model="paymentMethod" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 @error('paymentMethod') border-red-500 @enderror" placeholder="Tunai, Transfer, dll">
                        @error('paymentMethod') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Actions --}}
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">Batal</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="store">Simpan</span>
                            <span wire:loading wire:target="store">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
