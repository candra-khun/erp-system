<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Pengajuan Lembur (Overtime)</h1>
        <button wire:click="createOvertime" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            + Ajukan Lembur
        </button>
    </div>

    @if($errorMessage)
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari karyawan..." class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:w-96">
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Karyawan</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Tanggal</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Jam</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Total Jam</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Keterangan</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Disetujui Oleh</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($overtimes as $overtime)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                            {{ $overtime->employee?->full_name ?? '-' }}
                            <span class="block text-xs text-gray-400">{{ $overtime->employee?->employee_number }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ \Illuminate\Support\Carbon::parse($overtime->overtime_date)->translatedFormat('d M Y') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                            {{ \Illuminate\Support\Carbon::parse($overtime->start_time)->format('H:i') }} - {{ \Illuminate\Support\Carbon::parse($overtime->end_time)->format('H:i') }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ number_format((float) $overtime->hours, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $overtime->description ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm">
                            @php($color = match($overtime->status->value) {
                                'pending' => 'amber',
                                'approved' => 'green',
                                'rejected' => 'red',
                                default => 'gray',
                            })
                            <span class="rounded-full bg-{{ $color }}-100 px-2 py-0.5 text-xs font-medium text-{{ $color }}-700">{{ $overtime->status->label() }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $overtime->approver?->name ?? '-' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            @if($overtime->status->value === 'pending')
                                <button wire:click="approveOvertime({{ $overtime->id }})" class="mr-2 text-green-600 hover:text-green-800">Setujui</button>
                                <button wire:click="rejectOvertime({{ $overtime->id }})" class="mr-2 text-red-600 hover:text-red-800">Tolak</button>
                            @endif
                            @if($overtime->status->value !== 'cancelled')
                                <button wire:click="cancelOvertime({{ $overtime->id }})" class="text-gray-600 hover:text-gray-800">Batal</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada pengajuan lembur.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $overtimes->links() }}
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50" wire:click.self="$set('showModal', false)">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Ajukan Lembur</h3>

                <form wire:submit="saveOvertime" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Karyawan</label>
                        <select wire:model="employeeId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="">— Pilih Karyawan —</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }} ({{ $employee->employee_number }})</option>
                            @endforeach
                        </select>
                        @error('employeeId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Tanggal Lembur</label>
                        <input type="date" wire:model="overtimeDate" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        @error('overtimeDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Jam Mulai</label>
                            <input type="time" wire:model="startTime" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @error('startTime') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Jam Selesai</label>
                            <input type="time" wire:model="endTime" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @error('endTime') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Keterangan</label>
                        <input type="text" wire:model="description" placeholder="mis. Persiapan stok opname"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
