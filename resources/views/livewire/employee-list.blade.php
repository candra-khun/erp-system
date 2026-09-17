<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Daftar Karyawan</h1>
        <button wire:click="openCreate" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            + Tambah Karyawan
        </button>
    </div>

    @if($errorMessage)
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama / nomor / jabatan..." class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:w-96">
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Nomor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Nama</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Jabatan</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Departemen</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Cabang</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Gaji Pokok</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($employees as $employee)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $employee->employee_number }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ $employee->full_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $employee->position ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $employee->department ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $employee->warehouse?->name ?? 'Semua' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900">Rp {{ number_format((float) $employee->basic_salary, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if($employee->is_active)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Aktif</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button wire:click="openEdit({{ $employee->id }})" class="mr-2 text-blue-600 hover:text-blue-800">Edit</button>
                            <button wire:click="toggleActive({{ $employee->id }})" class="mr-2 text-gray-600 hover:text-gray-800">{{ $employee->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            <button wire:click="delete({{ $employee->id }})" wire:confirm="Yakin ingin menghapus karyawan ini?" class="text-red-600 hover:text-red-800">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada data karyawan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $employees->links() }}</div>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4" wire:click.self="$set('showModal', false)">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-gray-900">{{ $isEdit ? 'Edit Karyawan' : 'Tambah Karyawan' }}</h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nomor Karyawan <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="employee_number" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('employee_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="full_name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('full_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">NIK KTP</label>
                        <input type="text" wire:model="id_card_number" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('id_card_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">NPWP</label>
                        <input type="text" wire:model="npwp" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('npwp')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Jabatan</label>
                        <input type="text" wire:model="position" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('position')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Departemen</label>
                        <input type="text" wire:model="department" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('department')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
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
                        <label class="mb-1 block text-sm font-medium text-gray-700">Telepon</label>
                        <input type="text" wire:model="phone" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" wire:model="email" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tanggal Masuk</label>
                        <input type="date" wire:model="hire_date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('hire_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Gaji Pokok (Rp) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" wire:model="basic_salary" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('basic_salary')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tipe Pembayaran</label>
                        <select wire:model="payment_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="monthly">Bulanan</option>
                            <option value="daily">Harian</option>
                            <option value="hourly">Per Jam</option>
                        </select>
                        @error('payment_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Status Pajak</label>
                        <select wire:model="tax_status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="non_taxable">Tidak Kena Pajak</option>
                            <option value="taxable">Kena Pajak</option>
                        </select>
                        @error('tax_status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Bank</label>
                        <input type="text" wire:model="bank_name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('bank_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">No. Rekening</label>
                        <input type="text" wire:model="bank_account_number" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('bank_account_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Alamat</label>
                        <textarea wire:model="address" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                        @error('address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="$set('showModal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                    <button wire:click="save" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $isEdit ? 'Simpan Perubahan' : 'Tambah' }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
