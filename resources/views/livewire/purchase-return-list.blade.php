<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 class="text-2xl font-bold text-gray-900">Retur Pembelian</h1>
        <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
            + Buat Retur Baru
        </button>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('message') }}</div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nomor retur..." class="w-full sm:w-80 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 border">
    </div>

    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Retur</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Referensi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($returns as $pr)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $pr->return_number }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $pr->supplier?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $pr->purchaseOrder?->po_number ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php
                                $statusColors = [
                                    'draft' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                ];
                                $statusLabels = [
                                    'draft' => 'Draft',
                                    'approved' => 'Disetujui',
                                    'cancelled' => 'Dibatalkan',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$pr->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ $statusLabels[$pr->status] ?? ucfirst($pr->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $pr->return_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                            @if ($pr->status === 'draft')
                                <button wire:click="approve({{ $pr->id }})" wire:confirm="Yakin ingin menyetujui retur ini?" class="text-green-600 hover:text-green-900 font-medium">Setujui</button>
                                <button wire:click="cancel({{ $pr->id }})" wire:confirm="Yakin ingin membatalkan retur ini?" class="text-orange-600 hover:text-orange-900 font-medium">Batal</button>
                                <button wire:click="delete({{ $pr->id }})" wire:confirm="Yakin ingin menghapus retur ini?" class="text-red-600 hover:text-red-900 font-medium">Hapus</button>
                            @elseif ($pr->status === 'approved')
                                <button wire:click="cancel({{ $pr->id }})" wire:confirm="Yakin ingin membatalkan retur yang sudah disetujui? Stok akan dikembalikan." class="text-orange-600 hover:text-orange-900 font-medium">Batal</button>
                            @elseif ($pr->status === 'cancelled')
                                <button wire:click="delete({{ $pr->id }})" wire:confirm="Yakin ingin menghapus retur ini?" class="text-red-600 hover:text-red-900 font-medium">Hapus</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">Tidak ada data retur pembelian.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $returns->links() }}
    </div>

    {{-- Modal Form Create --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <form wire:submit.prevent="store">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">
                                Buat Retur Pembelian Baru
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanda Terima Barang (GRN)</label>
                                    <select wire:model.live="selectedGoodsReceiptId" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-3 py-2">
                                        <option value="">-- Pilih Tanda Terima --</option>
                                        @foreach ($availableReceipts as $receipt)
                                            <option value="{{ $receipt['id'] }}">{{ $receipt['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('selectedGoodsReceiptId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Retur</label>
                                    <input type="date" wire:model="returnDate" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-3 py-2">
                                    @error('returnDate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>

                                @if ($supplierName || $poNumber)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                                        <p class="text-sm text-gray-900 py-2">{{ $supplierName ?? '-' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor PO</label>
                                        <p class="text-sm text-gray-900 py-2">{{ $poNumber ?? '-' }}</p>
                                    </div>
                                @endif

                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                                    <textarea wire:model="notes" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-3 py-2" placeholder="Catatan tambahan..."></textarea>
                                    @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            @if (count($formItems) > 0)
                                <div class="border-t pt-4">
                                    <h4 class="text-sm font-medium text-gray-900 mb-3">Item Retur</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty Diterima</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty Retur</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Harga Satuan</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Alasan Retur</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                @foreach ($formItems as $index => $item)
                                                    <tr>
                                                        <td class="px-3 py-2 text-gray-900">{{ $item['product_name'] }}</td>
                                                        <td class="px-3 py-2 text-gray-500">{{ number_format($item['received_qty'], 2) }}</td>
                                                        <td class="px-3 py-2">
                                                            <input type="number" step="0.01" min="0.01" wire:model="formItems.{{ $index }}.quantity" class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-2 py-1">
                                                            @error("formItems.{$index}.quantity") <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <input type="number" step="0.01" min="0" wire:model="formItems.{{ $index }}.unit_price" class="w-28 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-2 py-1">
                                                            @error("formItems.{$index}.unit_price") <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <input type="text" wire:model="formItems.{{ $index }}.reason" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-2 py-1" placeholder="Alasan...">
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @elseif ($selectedGoodsReceiptId)
                                <p class="text-sm text-gray-500 italic mt-4">Tidak ada item pada tanda terima ini.</p>
                            @endif
                        </div>

                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Simpan Retur
                            </button>
                            <button type="button" wire:click="closeModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

</parameter>