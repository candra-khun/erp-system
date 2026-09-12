<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Produk</h1>
            <p class="text-gray-500 text-sm mt-1">Kelola data produk dan harga jual/beli.</p>
        </div>
        <button wire:click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Produk
        </button>
    </div>

    @if($showForm)
    <div class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">{{ $editId ? 'Edit Produk' : 'Tambah Produk Baru' }}</h2>
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SKU <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="sku" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="mis. PRD-001">
                        @error('sku') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Barcode</label>
                        <input type="text" wire:model="barcode" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="opsional, untuk scan kasir">
                        @error('barcode') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="name" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                        <select wire:model="product_category_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih Kategori</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('product_category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Satuan Dasar <span class="text-red-500">*</span></label>
                        <select wire:model="base_unit_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih Satuan</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->symbol }})</option>
                            @endforeach
                        </select>
                        @error('base_unit_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga Beli (Rp) <span class="text-red-500">*</span></label>
                        <input type="number" min="0" step="0.01" wire:model="purchase_price" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('purchase_price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual (Rp) <span class="text-red-500">*</span></label>
                        <input type="number" min="0" step="0.01" wire:model="selling_price" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('selling_price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stok Minimum</label>
                        <input type="number" min="0" step="0.01" wire:model="min_stock" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('min_stock') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reorder Point</label>
                        <input type="number" min="0" step="0.01" wire:model="reorder_point" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('reorder_point') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                    <textarea wire:model="description" rows="2" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Opsional"></textarea>
                </div>

                <div class="flex flex-col sm:flex-row gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Produk aktif
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="reorder_alert_enabled" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Aktifkan peringatan reorder
                    </label>
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
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama, SKU, atau barcode..." class="border-gray-300 rounded-md shadow-sm text-sm w-full sm:w-80 focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Produk</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Satuan</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Harga Beli</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Harga Jual</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Barcode</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($products as $product)
                <tr class="hover:bg-gray-50 {{ $product->is_active ? '' : 'opacity-50' }}">
                    <td class="px-5 py-3 text-sm font-mono text-gray-700">{{ $product->sku }}</td>
                    <td class="px-5 py-3 text-sm text-gray-900 font-medium">{{ $product->name }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $product->category->name ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $product->baseUnit->name ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-right text-gray-700">Rp {{ number_format((float) $product->purchase_price, 0, ',', '.') }}</td>
                    <td class="px-5 py-3 text-sm text-right font-medium text-gray-900">Rp {{ number_format((float) $product->selling_price, 0, ',', '.') }}</td>
                    <td class="px-5 py-3 text-sm text-center">
                        @if($previewId === $product->id && $product->barcode)
                            <div class="bg-white border border-gray-200 rounded-lg px-3 py-2 inline-block shadow-sm">
                                {!! app(\App\Services\BarcodeGeneratorService::class)->generateSvg($product->barcode) !!}
                            </div>
                        @elseif($product->barcode)
                            <button wire:click="previewBarcode({{ $product->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium" title="Klik untuk lihat barcode">Lihat</button>
                        @else
                            <span class="text-gray-400 text-xs">-</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-sm text-right space-x-2 whitespace-nowrap">
                        <button wire:click="openEdit({{ $product->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                        <button wire:click="delete({{ $product->id }})" wire:confirm="Yakin hapus produk ini (soft delete)?" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada data produk ditemukan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-200">
            {{ $products->links() }}
        </div>
    </div>
</div>
