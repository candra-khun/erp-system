<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 class="text-2xl font-bold text-gray-900">Chart of Accounts</h1>
        <button wire:click="openCreate" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 shadow-sm">
            + Tambah Akun
        </button>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 border border-green-200">
            {{ session('message') }}
        </div>
    @endif

    @if ($showForm)
        <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ $editId ? 'Edit Akun' : 'Tambah Akun' }}</h2>
            <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kode</label>
                    <input type="text" wire:model="code" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    @error('code') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
                    <select wire:model="type" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="asset">Asset</option>
                        <option value="liability">Liability</option>
                        <option value="equity">Equity</option>
                        <option value="revenue">Revenue</option>
                        <option value="expense">Expense</option>
                    </select>
                    @error('type') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Parent</label>
                    <select wire:model="parent_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">-- Tanpa Parent --</option>
                        @foreach ($parentAccounts as $pa)
                            <option value="{{ $pa->id }}">{{ $pa->code }} - {{ $pa->name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2 flex gap-2 mt-2">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Simpan</button>
                    <button type="button" wire:click="cancel" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Batal</button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode atau nama akun..."
            class="w-full sm:w-64 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Parent</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($accounts as $account)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $account->code }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $account->name }}</td>
                        <td class="px-4 py-3 text-sm">
                            @php
                                $typeColor = match($account->type) {
                                    'asset' => 'bg-blue-100 text-blue-700',
                                    'liability' => 'bg-red-100 text-red-700',
                                    'equity' => 'bg-purple-100 text-purple-700',
                                    'revenue' => 'bg-green-100 text-green-700',
                                    'expense' => 'bg-yellow-100 text-yellow-700',
                                    default => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $typeColor }}">
                                {{ ucfirst($account->type) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $account->parent?->code ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-center space-x-2">
                            <button wire:click="openEdit({{ $account->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            <button wire:click="delete({{ $account->id }})" wire:confirm="Yakin hapus akun ini?" class="text-red-600 hover:text-red-800 font-medium">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $accounts->links() }}
    </div>
</div>