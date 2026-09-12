<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Kategori Produk</h1>
            <p class="text-gray-500 text-sm mt-1">Kelola kategori produk.</p>
        </div>
        <button wire:click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Kategori
        </button>
    </div>

    @if($showForm)
    <div class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">{{ $editId ? 'Edit Kategori' : 'Tambah Kategori Baru' }}</h2>
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kategori <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="name" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kategori Induk</label>
                    <select wire:model="parent_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Tidak ada (kategori utama)</option>
                        @foreach($allCategories->where('id', '!=', $editId) as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Urutan</label>
                        <input type="number" min="0" wire:model="sort_order" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('sort_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            Aktif
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                    <textarea wire:model="description" rows="2" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Opsional"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">{{ $editId ? 'Perbarui' : 'Simpan' }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama kategori..." class="border-gray-300 rounded-md shadow-sm text-sm w-64 focus:border-blue-500 focus:ring-blue-500">
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Kategori</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Induk</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Jumlah Produk</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($categories as $category)
                <tr class="hover:bg-gray-50 {{ $category->is_active ? '' : 'opacity-50' }}">
                    <td class="px-5 py-3 text-sm text-gray-900 font-medium">
                        {{ $category->parent ? '— ' : '' }}{{ $category->name }}
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $category->parent?->name ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-center text-gray-600">{{ $category->products_count }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600 max-w-xs truncate">{{ $category->description ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-right space-x-2 whitespace-nowrap">
                        <button wire:click="openEdit({{ $category->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                        <button wire:click="delete({{ $category->id }})" wire:confirm="Yakin hapus kategori ini?" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada data kategori ditemukan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-4 border-t border-gray-200">
            {{ $categories->links() }}
        </div>
    </div>
</div>
