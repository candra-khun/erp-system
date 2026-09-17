<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Settlement Konsinyasi</h1>
        <button wire:click="openCreate" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            + Buat Settlement
        </button>
    </div>

    @if($errorMessage)
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nomor settlement / supplier..." class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:w-96">
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Nomor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Supplier</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Periode</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Qty Terjual</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Total Hutang</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($settlements as $settlement)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ $settlement->settlement_number }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $settlement->supplier?->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                            {{ $settlement->period_start?->format('d M Y') }} — {{ $settlement->period_end?->format('d M Y') }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900">{{ number_format((float) $settlement->total_quantity_sold, 2, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900">Rp {{ number_format((float) $settlement->total_amount - (float) $settlement->commission_amount, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-sm">
                            @php
                                $badges = ['draft' => 'gray', 'confirmed' => 'yellow', 'paid' => 'green', 'cancelled' => 'red'];
                                $badge = $badges[$settlement->status] ?? 'gray';
                                $labels = ['draft' => 'Draft', 'confirmed' => 'Dikonfirmasi', 'paid' => 'Lunas', 'cancelled' => 'Dibatalkan'];
                            @endphp
                            <span class="rounded-full bg-{{ $badge }}-100 px-2 py-0.5 text-xs font-medium text-{{ $badge }}-700">{{ $labels[$settlement->status] ?? ucfirst($settlement->status) }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            @if($settlement->status === 'draft')
                                <button wire:click="confirm({{ $settlement->id }})" wire:confirm="Konfirmasi settlement? Hutang ke supplier akan dibentuk + jurnal tercatat." class="text-green-600 hover:text-green-800">Konfirmasi</button>
                            @elseif($settlement->account_payable_id)
                                <a href="{{ route('account-payables.index') }}" class="text-blue-600 hover:text-blue-800">Lihat AP</a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada settlement konsinyasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $settlements->links() }}</div>

    @if($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4" wire:click.self="$set('showForm', false)">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-gray-900">Buat Settlement Konsinyasi</h2>

                <p class="mb-4 text-sm text-gray-600">
                    Sistem akan menghitung seluruh barang konsinyasi yang terjual dari supplier terpilih dan membentuk hutang (AP) ke supplier.
                </p>

                <div class="space-y-4">
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
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Komisi Toko (Rp)</label>
                        <input type="number" step="0.01" wire:model="commission" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('commission')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="$set('showForm', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                    <button wire:click="create" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Hitung & Buat</button>
                </div>
            </div>
        </div>
    @endif
</div>
