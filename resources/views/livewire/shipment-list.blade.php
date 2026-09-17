<div class="space-y-4">
    @if(session()->has('success'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session()->has('error'))
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xl font-bold text-gray-900">Surat Jalan & Pengiriman</h2>
        <div class="flex gap-2">
            <button wire:click="$toggle('showCourierForm')" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Kurir</button>
            <button wire:click="openForm" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">+ Surat Jalan</button>
        </div>
    </div>

    {{-- Form surat jalan --}}
    @if($showForm)
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 space-y-3">
            <h3 class="font-semibold text-blue-900">Buat Surat Jalan</h3>
            @if($errorMessage)
                <p class="text-sm text-red-600">{{ $errorMessage }}</p>
            @endif
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Sales Order</label>
                    <select wire:model="salesOrderId" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="">— Pilih SO —</option>
                        @foreach($this->deliverableOrders as $so)
                            <option value="{{ $so->id }}">{{ $so->so_number }} — {{ $so->customer?->name ?? 'Umum' }} ({{ $so->status }})</option>
                        @endforeach
                    </select>
                    @error('salesOrderId')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Kurir</label>
                    <select wire:model="courierId" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="">— Tanpa kurir —</option>
                        @foreach($this->courierOptions as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->cost_per_kg }}/kg)</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Nama Penerima</label>
                    <input wire:model="recipientName" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Telepon Penerima</label>
                    <input wire:model="recipientPhone" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-700">Alamat Tujuan</label>
                    <textarea wire:model="destinationAddress" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Berat (kg)</label>
                    <input wire:model="totalWeightKg" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Ongkir (kosongkan = hitung otomatis)</label>
                    <input wire:model="shippingCost" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-700">Catatan</label>
                    <input wire:model="notes" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </div>
            </div>
            <div class="flex gap-2">
                <button wire:click="saveShipment" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan</button>
                <button wire:click="$set('showForm', false)" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
            </div>
        </div>
    @endif

    {{-- Form kurir --}}
    @if($showCourierForm)
        <div class="rounded-lg border border-gray-300 bg-gray-50 p-4 space-y-3">
            <h3 class="font-semibold text-gray-900">{{ $courierEditId ? 'Edit' : 'Tambah' }} Kurir</h3>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Kode</label>
                    <input wire:model="courierCode" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    @error('courierCode')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Nama</label>
                    <input wire:model="courierName" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    @error('courierName')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Tipe</label>
                    <select wire:model="courierType" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="internal">Internal</option>
                        <option value="external">Eksternal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Telepon</label>
                    <input wire:model="courierPhone" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Biaya / kg</label>
                    <input wire:model="courierCostPerKg" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input wire:model="courierActive" type="checkbox" class="rounded"> Aktif
                </label>
            </div>
            <div class="flex gap-2">
                <button wire:click="saveCourier" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan</button>
                <button wire:click="$set('showCourierForm', false)" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
            </div>
        </div>
    @endif

    {{-- Filter --}}
    <div class="flex flex-wrap gap-2">
        <input wire:model.live="search" placeholder="Cari nomor SJ / SO / penerima…" class="flex-1 rounded-md border-gray-300 text-sm sm:max-w-xs">
        <select wire:model.live="statusFilter" class="rounded-md border-gray-300 text-sm">
            <option value="">Semua status</option>
            @foreach($this->statuses as $s)
                <option value="{{ $s['value'] }}">{{ $s['label'] }}</option>
            @endforeach
        </select>
    </div>

    {{-- Tabel surat jalan --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-3 py-2">No. SJ</th>
                    <th class="px-3 py-2">SO / Pelanggan</th>
                    <th class="px-3 py-2">Kurir</th>
                    <th class="px-3 py-2">Tujuan</th>
                    <th class="px-3 py-2">Ongkir</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->shipments as $sh)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium text-gray-900">{{ $sh->shipment_number }}</td>
                        <td class="px-3 py-2">
                            <div>{{ $sh->salesOrder?->so_number }}</div>
                            <div class="text-xs text-gray-500">{{ $sh->salesOrder?->customer?->name }}</div>
                        </td>
                        <td class="px-3 py-2">{{ $sh->courier?->name ?? '—' }}</td>
                        <td class="max-w-[16rem] truncate px-3 py-2" title="{{ $sh->destination_address }}">{{ $sh->recipient_name }}</td>
                        <td class="px-3 py-2">Rp {{ number_format((float) $sh->shipping_cost, 0, ',', '.') }}</td>
                        <td class="px-3 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ [
                                'preparing' => 'bg-yellow-100 text-yellow-800',
                                'dispatched' => 'bg-blue-100 text-blue-800',
                                'in_transit' => 'bg-indigo-100 text-indigo-800',
                                'delivered' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                            ][$sh->status->value] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ $sh->status->label() }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-xs">
                            <button wire:click="printDeliveryNote({{ $sh->id }})" class="text-gray-700 hover:text-gray-900 font-medium mr-2" title="Cetak surat jalan (PDF)">Cetak</button>
                            @if($sh->status->value === 'preparing')
                                <button wire:click="dispatchShipment({{ $sh->id }})" class="text-blue-600 hover:text-blue-800 font-medium">Kirim</button>
                                <button wire:click="cancelShipment({{ $sh->id }})" wire:confirm="Batalkan surat jalan ini?" class="text-red-600 hover:text-red-800 font-medium">Batal</button>
                            @elseif($sh->status->value === 'dispatched')
                                <button wire:click="markInTransit({{ $sh->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Perjalanan</button>
                                <button wire:click="markDelivered({{ $sh->id }})" class="text-green-600 hover:text-green-800 font-medium">Diterima</button>
                            @elseif($sh->status->value === 'in_transit')
                                <button wire:click="markDelivered({{ $sh->id }})" class="text-green-600 hover:text-green-800 font-medium">Diterima</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-8 text-center text-gray-500">Belum ada surat jalan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->shipments->links() }}

    {{-- Daftar kurir --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-3 py-2" colspan="4">Master Kurir</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->couriers as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-mono text-xs">{{ $c->code }}</td>
                        <td class="px-3 py-2">{{ $c->name }} <span class="text-xs text-gray-500">({{ $c->type }})</span></td>
                        <td class="px-3 py-2">Rp {{ number_format((float) $c->cost_per_kg, 0, ',', '.') }}/kg</td>
                        <td class="px-3 py-2 text-right">
                            <button wire:click="editCourier({{ $c->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                            <button wire:click="deleteCourier({{ $c->id }})" wire:confirm="Hapus kurir ini?" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500 text-sm">Belum ada kurir.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
