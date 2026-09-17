<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Penerimaan Konsinyasi</h1>
        <button wire:click="openCreate" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            + Terima Konsinyasi
        </button>
    </div>

    @if($errorMessage)
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nomor / supplier..." class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:w-96">
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Nomor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Supplier</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Cabang</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Tgl Terima</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Item</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($consignments as $consignment)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ $consignment->consignment_number }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $consignment->supplier?->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $consignment->warehouse?->name ?? 'Semua' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $consignment->received_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm">
                            @php
                                $badges = ['open' => 'blue', 'partially_settled' => 'yellow', 'settled' => 'green', 'expired' => 'gray', 'cancelled' => 'red'];
                                $badge = $badges[$consignment->status] ?? 'gray';
                                $labels = ['open' => 'Terbuka', 'partially_settled' => 'Sebagian Settle', 'settled' => 'Disettle', 'expired' => 'Kedaluwarsa', 'cancelled' => 'Dibatalkan'];
                            @endphp
                            <span class="rounded-full bg-{{ $badge }}-100 px-2 py-0.5 text-xs font-medium text-{{ $badge }}-700">{{ $labels[$consignment->status] ?? ucfirst($consignment->status) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="space-y-1">
                                @foreach($consignment->items as $item)
                                    <div class="text-xs text-gray-600">
                                        {{ $item->product?->name }} — terima {{ $item->quantity_received }} / terjual {{ $item->quantity_sold }} / sisa {{ $item->quantity_available }}
                                        @if($item->quantity_available > 0)
                                            <button wire:click="returnItem({{ $item->id }}, {{ (float) $item->quantity_available }})" class="ml-2 text-red-600 hover:text-red-800">Kembalikan Semua</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada penerimaan konsinyasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $consignments->links() }}</div>

    @if($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4" wire:click.self="$set('showForm', false)">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-gray-900">Terima Barang Konsinyasi</h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Supplier <span class="text-red-500">*</span></label>
                        <select wire:model="supplierId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">— Pilih Supplier —</option>
                            @foreach($supplierOptions as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        @error('supplierId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Cabang</label>
                        <select wire:model="warehouseId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Semua Cabang</option>
                            @foreach($warehouseOptions as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouseId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tanggal Kadaluwarsa Titipan</label>
                        <input type="date" wire:model="expiry_date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('expiry_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-6 space-y-2">
                    <h3 class="text-sm font-semibold text-gray-700">Detail Barang</h3>
                    @foreach($lines as $i => $line)
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="flex-1">
                                <label class="mb-1 block text-xs font-medium text-gray-700">Produk</label>
                                <select wire:model="lines.{{ $i }}.product_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    <option value="0">— Pilih Produk —</option>
                                    @foreach($productOptions as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                                    @endforeach
                                </select>
                                @error("lines.{$i}.product_id")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="w-24">
                                <label class="mb-1 block text-xs font-medium text-gray-700">Qty</label>
                                <input type="number" step="0.01" wire:model="lines.{{ $i }}.quantity" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @error("lines.{$i}.quantity")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="w-36">
                                <label class="mb-1 block text-xs font-medium text-gray-700">Harga Titip</label>
                                <input type="number" step="0.01" wire:model="lines.{{ $i }}.consignment_price" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @error("lines.{$i}.consignment_price")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="w-36">
                                <label class="mb-1 block text-xs font-medium text-gray-700">Harga Jual</label>
                                <input type="number" step="0.01" wire:model="lines.{{ $i }}.selling_price" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @error("lines.{$i}.selling_price")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <button wire:click="removeLine({{ $i }})" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-red-600 hover:bg-red-50">Hapus</button>
                        </div>
                    @endforeach
                </div>

                <button wire:click="addLine" class="mt-3 rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">+ Tambah Baris</button>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="$set('showForm', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                    <button wire:click="save" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Terima Konsinyasi</button>
                </div>
            </div>
        </div>
    @endif
</div>
