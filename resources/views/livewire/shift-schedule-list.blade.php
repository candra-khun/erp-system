<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Penugasan Shift Karyawan</h1>
        <button wire:click="createSchedule" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            + Tugaskan Shift
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
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Shift</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Berlaku Mulai</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Sampai</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Sumber</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($schedules as $schedule)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                            {{ $schedule->employee?->full_name ?? '-' }}
                            <span class="block text-xs text-gray-400">{{ $schedule->employee?->employee_number }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                            {{ $schedule->workShift?->name ?? '-' }}
                            <span class="block text-xs text-gray-400">{{ $schedule->workShift?->time_range }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ \Illuminate\Support\Carbon::parse($schedule->effective_date)->translatedFormat('d M Y') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                            {{ $schedule->end_date ? \Illuminate\Support\Carbon::parse($schedule->end_date)->translatedFormat('d M Y') : 'Sampai diubah' }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if($schedule->source->value === 'recurring')
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Berulang</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Manual</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button wire:click="editSchedule({{ $schedule->id }})" class="mr-2 text-blue-600 hover:text-blue-800">Edit</button>
                            <button wire:click="deleteSchedule({{ $schedule->id }})" wire:confirm="Hapus penugasan shift ini?" class="text-red-600 hover:text-red-800">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada penugasan shift.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $schedules->links() }}
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50" wire:click.self="$set('showModal', false)">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ $isEdit ? 'Edit Penugasan Shift' : 'Tugaskan Shift' }}</h3>

                <form wire:submit="saveSchedule" class="space-y-4">
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
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Shift</label>
                        <select wire:model="workShiftId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="">— Pilih Shift —</option>
                            @foreach($workShifts as $shift)
                                <option value="{{ $shift->id }}">{{ $shift->name }} ({{ $shift->time_range }})</option>
                            @endforeach
                        </select>
                        @error('workShiftId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Berlaku Mulai</label>
                            <input type="date" wire:model="effectiveDate" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @error('effectiveDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Berakhir (Opsional)</label>
                            <input type="date" wire:model="endDate" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @error('endDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="recurring" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Berulang setiap minggu (jadwal tetap)
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
