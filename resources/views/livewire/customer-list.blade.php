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
</div>
