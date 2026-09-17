<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Channel Marketplace</h1>
        <button wire:click="openCreate" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            + Tambah Channel
        </button>
    </div>

    @if($errorMessage)
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari channel / toko..." class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:w-96">
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Channel</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Platform</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Cabang</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Order</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Sync Stok</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($channels as $channel)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                            {{ $channel->name }}
                            <div class="text-xs text-gray-500">{{ $channel->shop_name ?? '-' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ ucfirst($channel->platform) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $channel->warehouse?->name ?? 'Semua' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900">{{ $channel->orders->count() }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if($channel->sync_stock)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Aktif</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if($channel->status === 'active')
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Aktif</span>
                            @elseif($channel->status === 'error')
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Error</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button wire:click="syncStock({{ $channel->id }})" class="mr-2 text-blue-600 hover:text-blue-800">Sync Stok</button>
                            <button wire:click="toggleStatus({{ $channel->id }})" class="text-gray-600 hover:text-gray-800">{{ $channel->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada channel marketplace.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $channels->links() }}</div>

    @if($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4" wire:click.self="$set('showForm', false)">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-gray-900">Tambah Channel Marketplace</h2>

                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nama Channel <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="name" placeholder="mis. Tokopedia - Toko Saya" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Platform <span class="text-red-500">*</span></label>
                        <select wire:model="platform" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="tokopedia">Tokopedia</option>
                            <option value="shopee">Shopee</option>
                            <option value="lazada">Lazada</option>
                            <option value="bukalapak">Bukalapak</option>
                            <option value="tokotalk">Tokotalk</option>
                            <option value="other">Lainnya</option>
                        </select>
                        @error('platform')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nama Toko</label>
                        <input type="text" wire:model="shop_name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('shop_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Cabang Default</label>
                        <select wire:model="warehouseId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Semua Cabang</option>
                            @foreach($warehouseOptions as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouseId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Kredensial API (JSON)</label>
                        <textarea wire:model="api_credential" rows="3" placeholder='{"api_key":"...","shop_id":"..."}' class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-xs"></textarea>
                        @error('api_credential')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="sync_orders" class="rounded border-gray-300"> Sync Order
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="sync_stock" class="rounded border-gray-300"> Sync Stok
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="$set('showForm', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                    <button wire:click="save" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
