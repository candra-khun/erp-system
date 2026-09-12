<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 class="text-2xl font-bold text-gray-900">Hutang Supplier</h1>
        <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
            + Tambah Hutang Baru
        </button>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('message') }}</div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-4 flex flex-col sm:flex-row gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nomor hutang..." class="w-full sm:w-80 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 border text-sm">
        <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 border text-sm">
            <option value="">Semua Status</option>
            <option value="open">Belum Dibayar</option>
            <option value="partial">Sebagian</option>
            <option value="paid">Lunas</option>
            <option value="overdue">Jatuh Tempo</option>
        </select>
    </div>

    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Hutang</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Terbayar</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jatuh Tempo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($payables as $ap)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $ap->ap_number }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $ap->supplier?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">Rp {{ number_format((float) $ap->total_amount, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">Rp {{ number_format((float) $ap->paid_amount, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">Rp {{ number_format((float) $ap->remaining_amount, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $ap->due_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php
                                $statusColors = [
                                    'open' => 'bg-yellow-100 text-yellow-800',
                                    'partial' => 'bg-blue-100 text-blue-800',
                                    'paid' => 'bg-green-100 text-green-800',
                                    'overdue' => 'bg-red-100 text-red-800',
                                ];
                                $statusLabels = [
                                    'open' => 'Belum Dibayar',
                                    'partial' => 'Sebagian',
                                    'paid' => 'Lunas',
                                    'overdue' => 'Jatuh Tempo',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$ap->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ $statusLabels[$ap->status] ?? ucfirst($ap->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                            @if ($ap->status !== 'paid')
                                <button wire:click="openPayModal({{ $ap->id }})" class="text-indigo-600 hover:text-indigo-900 font-medium">Bayar</button>
                            @endif
                            @if ($ap->status === 'open')
                                <button wire:click="delete({{ $ap->id }})" wire:confirm="Yakin ingin menghapus data hutang ini?" class="text-red-600 hover:text-red-900 font-medium">Hapus</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">Tidak ada data hutang supplier.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $payables->links() }}
    </div>

    {{-- Modal Create --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModals"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit.prevent="store">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Tambah Hutang Baru</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                                    <select wire:model="supplier_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-3 py-2">
                                        <option value="">-- Pilih Supplier --</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('supplier_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Total Jumlah (Rp)</label>
                                    <input type="number" step="0.01" min="0" wire:model="total_amount" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-3 py-2">
                                    @error('total_amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Jatuh Tempo</label>
                                    <input type="date" wire:model="due_date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-3 py-2">
                                    @error('due_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                                    <textarea wire:model="notes" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-3 py-2" placeholder="Catatan tambahan..."></textarea>
                                    @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Referensi</label>
                                        <input type="text" wire:model="reference_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-3 py-2" placeholder="Opsional">
                                        @error('reference_type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Referensi</label>
                                        <input type="number" wire:model="reference_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-3 py-2" placeholder="Opsional">
                                        @error('reference_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">Simpan</button>
                            <button type="button" wire:click="closeModals" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Pay --}}
    @if ($showPayModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModals"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                    <form wire:submit.prevent="pay">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Bayar Hutang</h3>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Pembayaran (Rp)</label>
                                <input type="number" step="0.01" min="0" wire:model="pay_amount" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-3 py-2">
                                @error('pay_amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">Proses Pembayaran</button>
                            <button type="button" wire:click="closeModals" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
