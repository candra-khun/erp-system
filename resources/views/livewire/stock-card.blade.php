<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Kartu Stok</h1>
            <p class="text-gray-500 text-sm mt-1">Riwayat pergerakan stok (masuk/keluar) per produk dan gudang.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex flex-wrap gap-3 items-center">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau SKU produk..." class="border-gray-300 rounded-md shadow-sm text-sm w-64 focus:border-blue-500 focus:ring-blue-500">

            <select wire:model.live="filterProductId" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Semua Produk</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->sku }} - {{ $product->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterWarehouseId" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Semua Gudang</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterType" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Semua Tipe</option>
                <option value="in">Masuk</option>
                <option value="out">Keluar</option>
                <option value="adjustment">Penyesuaian</option>
                <option value="transfer_in">Transfer Masuk</option>
                <option value="transfer_out">Transfer Keluar</option>
            </select>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gudang</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referensi</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Catatan</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Oleh</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($movements as $movement)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $movement->movement_date?->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-gray-900 font-medium">
                        <span class="font-mono text-xs text-gray-500">{{ $movement->product->sku ?? '' }}</span><br>
                        {{ $movement->product->name ?? '-' }}
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $movement->warehouse->name ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm">
                        @php
                            $typeColors = [
                                'in' => 'bg-green-100 text-green-800',
                                'out' => 'bg-red-100 text-red-800',
                                'adjustment' => 'bg-yellow-100 text-yellow-800',
                                'transfer_in' => 'bg-blue-100 text-blue-800',
                                'transfer_out' => 'bg-orange-100 text-orange-800',
                            ];
                            $typeLabels = [
                                'in' => 'Masuk',
                                'out' => 'Keluar',
                                'adjustment' => 'Penyesuaian',
                                'transfer_in' => 'Transfer Masuk',
                                'transfer_out' => 'Transfer Keluar',
                            ];
                            $color = $typeColors[$movement->type] ?? 'bg-gray-100 text-gray-800';
                            $label = $typeLabels[$movement->type] ?? $movement->type;
                        @endphp
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $color }}">{{ $label }}</span>
                    </td>
                    <td class="px-5 py-3 text-sm text-right font-mono font-semibold {{ str_contains($movement->type, 'out') ? 'text-red-600' : 'text-green-600' }}">
                        {{ str_contains($movement->type, 'out') ? '-' : '+' }}{{ number_format((float) $movement->quantity, 0, ',', '.') }}
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-500">{{ $movement->reference_type ? class_basename($movement->reference_type) . ' #' . $movement->reference_id : '-' }}</td>
                    <td class="px-5 py-3 text-sm text-gray-500 max-w-xs truncate">{{ $movement->notes ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-gray-500">{{ $movement->creator->name ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada data mutasi stok ditemukan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-5 py-4 border-t border-gray-200">
            {{ $movements->links() }}
        </div>
    </div>
</div>
