<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 class="text-2xl font-bold text-gray-900">Goods Receipt List</h1>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('message') }}</div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari GR Number..." class="w-full sm:w-80 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 border">
    </div>

    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GR Number</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Terima</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gudang</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($goodsReceipts as $gr)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $gr->grn_number }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $gr->purchaseOrder?->po_number ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $gr->receipt_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $gr->warehouse?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                            <button wire:click="delete({{ $gr->id }})" wire:confirm="Yakin ingin menghapus?" class="text-red-600 hover:text-red-900">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">Tidak ada data goods receipt.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $goodsReceipts->links() }}
    </div>
</div>

</parameter>