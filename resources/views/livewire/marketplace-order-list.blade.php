<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Order Marketplace</h1>
        <p class="mt-1 text-sm text-gray-600">Order dari channel yang siap dikonversi menjadi sales order.</p>
    </div>

    @if($errorMessage ?? null)
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    <div class="mb-4 flex flex-wrap gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nomor order / pelanggan..." class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:w-72">
        <select wire:model.live="channelId" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Channel</option>
            @foreach($channelOptions as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Status</option>
            <option value="new">Baru</option>
            <option value="converted">Dikonversi</option>
            <option value="shipped">Dikirim</option>
            <option value="delivered">Diterima</option>
            <option value="cancelled">Dibatalkan</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">No. Order Channel</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Channel</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Pelanggan</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Total</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Kurir</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($orders as $order)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ $order->channel_order_number ?? $order->channel_order_id ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $order->channel?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $order->customer_name ?? '-' }}
                            <div class="text-xs text-gray-500">{{ $order->customer_phone ?? '' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900">Rp {{ number_format((float) $order->total_amount, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $order->courier_name ?? '-' }}
                            <div class="text-xs text-gray-500">{{ $order->tracking_number ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if($order->status === 'converted')
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Dikonversi</span>
                            @elseif($order->status === 'new')
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Baru</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ ucfirst($order->status) }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            @if($order->sales_order_id)
                                <a href="{{ route('sales-orders.index') }}" class="text-blue-600 hover:text-blue-800">Lihat SO</a>
                            @else
                                <button wire:click="openConvert({{ $order->id }})" class="text-green-600 hover:text-green-800">Konversi ke SO</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada order marketplace.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>

    @if($convertId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4" wire:click.self="$set('convertId', null)">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-gray-900">Konversi Order menjadi Sales Order</h2>

                <div class="space-y-2">
                    @foreach($convertLines as $i => $line)
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="flex-1">
                                <label class="mb-1 block text-xs font-medium text-gray-700">Produk</label>
                                <select wire:model="convertLines.{{ $i }}.product_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    <option value="0">— Pilih Produk —</option>
                                    @foreach($productOptions as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                                    @endforeach
                                </select>
                                @error("convertLines.{$i}.product_id")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="w-24">
                                <label class="mb-1 block text-xs font-medium text-gray-700">Qty</label>
                                <input type="number" step="0.01" wire:model="convertLines.{{ $i }}.quantity" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @error("convertLines.{$i}.quantity")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="w-32">
                                <label class="mb-1 block text-xs font-medium text-gray-700">Harga Satuan</label>
                                <input type="number" step="0.01" wire:model="convertLines.{{ $i }}.unit_price" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @error("convertLines.{$i}.unit_price")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <button wire:click="removeConvertLine({{ $i }})" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-red-600 hover:bg-red-50">Hapus</button>
                        </div>
                    @endforeach
                </div>

                <button wire:click="addConvertLine" class="mt-3 rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">+ Tambah Baris</button>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="$set('convertId', null)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                    <button wire:click="convert" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Konversi</button>
                </div>
            </div>
        </div>
    @endif
</div>
