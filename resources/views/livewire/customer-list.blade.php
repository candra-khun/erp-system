<div>
    @if(session('message'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">
            {{ session('message') }}
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Daftar Customer</h1>
        <button wire:click="openCreate" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            + Tambah Customer
        </button>
    </div>

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari customer..." class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:w-80">
    </div>

    <div class="mb-4">
        <livewire:master-data-import-panel type="customers" />
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Telepon</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Tipe</th>
                    <th class="px-4 py-3">Alamat</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($customers as $customer)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $customer->name }}</td>
                        <td class="px-4 py-3">{{ $customer->phone ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $customer->email ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @php $ct = \App\Enums\CustomerType::tryFrom($customer->type); @endphp
                            {{ $ct ? $ct->label() : ($customer->type ?? '-') }}
                        </td>
                        <td class="px-4 py-3 max-w-xs truncate">{{ $customer->address ?? '-' }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button wire:click="openLoyalty({{ $customer->id }})" class="text-amber-600 hover:text-amber-800">Poin</button>
                            <button wire:click="openEdit({{ $customer->id }})" class="text-blue-600 hover:text-blue-800">Edit</button>
                            <button wire:click="delete({{ $customer->id }})" wire:confirm="Yakin ingin menghapus customer ini?" class="text-red-600 hover:text-red-800">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada data customer.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-gray-900">{{ $isEdit ? 'Edit Customer' : 'Tambah Customer' }}</h2>
                <form wire:submit="save">
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Nama <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @error('name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Telepon</label>
                            <input type="text" wire:model="phone" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @error('phone') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" wire:model="email" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @error('email') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Tipe Customer <span class="text-red-500">*</span></label>
                            <select wire:model="customer_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                <option value="">-- Pilih Tipe --</option>
                                @foreach(\App\Enums\CustomerType::cases() as $ct)
                                    <option value="{{ $ct->value }}">{{ $ct->label() }}</option>
                                @endforeach
                            </select>
                            @error('customer_type') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Alamat</label>
                            <textarea wire:model="address" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                            @error('address') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $isEdit ? 'Perbarui' : 'Simpan' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showLoyalty && $this->loyaltyCustomer)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Loyalitas — {{ $this->loyaltyCustomer->name }}</h2>
                        <p class="text-xs text-gray-500">Tipe: {{ $this->loyaltyCustomer->type ?? '-' }}</p>
                    </div>
                    <button wire:click="$set('showLoyalty', false)" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                @if($this->loyaltyCustomer->type === 'member')
                    <div class="mb-4 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-lg bg-gray-50 p-3">
                            <div class="text-xs text-gray-500">Tier</div>
                            <div class="font-bold text-amber-600">{{ $this->loyaltyProfile->tier->label() }}</div>
                            <div class="text-[10px] text-gray-400">Diskon {{ $this->loyaltyProfile->tier->discountPercent() }}%</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <div class="text-xs text-gray-500">Poin Saat Ini</div>
                            <div class="font-bold">{{ number_format($this->loyaltyProfile->points_balance, 0, ',', '.') }}</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <div class="text-xs text-gray-500">Poin Akumulasi</div>
                            <div class="font-bold">{{ number_format($this->loyaltyProfile->lifetime_points, 0, ',', '.') }}</div>
                        </div>
                    </div>

                    {{-- Redeem --}}
                    <div class="mb-4 rounded-lg border border-gray-200 p-3">
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">Tukar Poin</h3>
                        <div class="flex flex-wrap items-end gap-2">
                            <div>
                                <label class="mb-1 block text-xs text-gray-500">Jumlah poin</label>
                                <input type="number" min="1" wire:model="redeemPoints" class="w-28 rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                            </div>
                            <div class="flex-1">
                                <label class="mb-1 block text-xs text-gray-500">Catatan</label>
                                <input type="text" wire:model="redeemNotes" class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                            </div>
                            <button wire:click="redeem" class="rounded-lg bg-amber-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-amber-700">Tukar</button>
                        </div>
                        @error('redeemPoints') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    {{-- Riwayat poin --}}
                    <div class="mb-4">
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">Riwayat Poin (10 terakhir)</h3>
                        <div class="max-h-40 overflow-y-auto rounded-lg border border-gray-200">
                            <table class="w-full text-left text-xs">
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($this->loyaltyLogs as $log)
                                        <tr>
                                            <td class="px-3 py-1.5">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-3 py-1.5">{{ $log->type }}</td>
                                            <td class="px-3 py-1.5 {{ $log->points >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $log->points >= 0 ? '+' : '' }}{{ number_format($log->points) }}</td>
                                            <td class="px-3 py-1.5 text-gray-500">saldo {{ number_format($log->balance_after) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="px-3 py-3 text-center text-gray-400">Belum ada poin.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <p class="mb-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-600">Customer ini bukan member — poin loyalitas hanya berlaku untuk tipe member. Ubah tipe melalui Edit.</p>
                @endif

                {{-- Catat komunikasi --}}
                <div class="mb-2 rounded-lg border border-gray-200 p-3">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Catat Komunikasi</h3>
                    <div class="flex flex-wrap gap-2">
                        <select wire:model="commChannel" class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
                            <option value="phone">Telepon</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="email">Email</option>
                            <option value="visit">Kunjungan</option>
                            <option value="other">Lainnya</option>
                        </select>
                        <input type="text" wire:model="commSubject" placeholder="Subjek" class="w-32 rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                        <input type="date" wire:model="commFollowUpDate" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    </div>
                    <textarea wire:model="commSummary" rows="2" placeholder="Ringkasan percakapan…" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm"></textarea>
                    @error('commSummary') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    <button wire:click="addCommunication" class="mt-2 rounded-lg bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Simpan</button>
                </div>

                {{-- Riwayat komunikasi --}}
                <div>
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Riwayat Komunikasi</h3>
                    <div class="max-h-40 space-y-2 overflow-y-auto">
                        @forelse($this->communications as $comm)
                            <div class="rounded-lg bg-gray-50 p-2 text-xs">
                                <div class="flex justify-between">
                                    <span class="font-medium">{{ $comm->channel }}</span>
                                    <span class="text-gray-400">{{ $comm->created_at->format('d/m/Y H:i') }} · {{ $comm->creator?->name ?? 'sistem' }}</span>
                                </div>
                                @if($comm->subject)<div class="font-medium text-gray-700">{{ $comm->subject }}</div>@endif
                                <div class="text-gray-600">{{ $comm->summary }}</div>
                                @if($comm->follow_up_date)<div class="text-amber-600">Follow-up: {{ $comm->follow_up_date->format('d/m/Y') }}</div>@endif
                            </div>
                        @empty
                            <p class="text-center text-xs text-gray-400">Belum ada riwayat.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
