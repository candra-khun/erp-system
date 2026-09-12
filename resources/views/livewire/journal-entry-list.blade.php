<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 class="text-2xl font-bold text-gray-900">Journal Entries</h1>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700 border border-green-200">{{ session('message') }}</div>
    @endif

    <div class="mb-4 flex flex-col sm:flex-row gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari referensi / deskripsi..."
            class="w-full sm:w-80 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-4 py-2" />
        <select wire:model.live="statusFilter"
            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-4 py-2">
            <option value="">Semua Status</option>
            <option value="posted">Posted</option>
            <option value="draft">Draft</option>
        </select>
        <input type="date" wire:model.live="dateFrom" title="Dari tanggal" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500 border px-3 py-2">
        <input type="date" wire:model.live="dateTo" title="Sampai tanggal" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500 border px-3 py-2">
    </div>

    <div class="overflow-x-auto bg-white rounded-lg shadow border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($entries as $entry)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $entry->journal_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $entry->journal_number ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">{{ $entry->description ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if ($entry->is_posted)
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Posted</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">Draft</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                            <button wire:click="delete({{ $entry->id }})" wire:confirm="Yakin ingin menghapus jurnal ini?" class="text-red-600 hover:text-red-900">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500 text-sm">Tidak ada data journal entry.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $entries->links() }}
    </div>
</div>

</parameter>