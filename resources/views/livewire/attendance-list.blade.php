<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Absensi Karyawan</h1>
        <div class="flex gap-2">
            <button wire:click="generateDaily" wire:confirm="Generate absensi untuk karyawan yang belum tercatat pada tanggal terpilih?"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Generate Harian
            </button>
            <button wire:click="openClockForm" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                + Catat Absensi
            </button>
        </div>
    </div>

    @if($errorMessage)
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    {{-- Filter bar --}}
    <div class="mb-4 flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <div class="flex-1">
            <label class="mb-1 block text-xs font-semibold text-gray-600">Cari Karyawan</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Nama / nomor karyawan..."
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-gray-600">Mode Tampilan</label>
            <select wire:model.live="viewMode" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="daily">Harian</option>
                <option value="monthly">Rekap Bulanan</option>
            </select>
        </div>

        @if($viewMode === 'daily')
            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Tanggal</label>
                <input type="date" wire:model.live="filterDate" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
        @else
            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Bulan</label>
                <select wire:model.live="filterMonth" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">{{ \Illuminate\Support\Carbon::create(2020, $m, 1)->translatedFormat('F') }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Tahun</label>
                <input type="number" min="2020" max="2100" wire:model.live="filterYear" class="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
        @endif
    </div>

    @if($viewMode === 'daily')
        {{-- Tabel absensi harian --}}
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Karyawan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Shift</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Clock In</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Clock Out</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Telat</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Lembur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Metode</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($attendances as $attendance)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                {{ $attendance->employee?->full_name ?? '-' }}
                                <span class="block text-xs text-gray-400">{{ $attendance->employee?->employee_number }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ \Illuminate\Support\Carbon::parse($attendance->attendance_date)->translatedFormat('d M Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $attendance->workShift?->time_range ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                {{ $attendance->clock_in ? \Illuminate\Support\Carbon::parse($attendance->clock_in)->format('H:i') : '-' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                {{ $attendance->clock_out ? \Illuminate\Support\Carbon::parse($attendance->clock_out)->format('H:i') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @php($color = $attendance->status->color())
                                <span class="rounded-full bg-{{ $color }}-100 px-2 py-0.5 text-xs font-medium text-{{ $color }}-700">{{ $attendance->status->label() }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600">{{ $attendance->late_minutes ? $attendance->late_minutes.' mnt' : '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600">{{ ((float) $attendance->overtime_hours) > 0 ? number_format((float) $attendance->overtime_hours, 2).' jam' : '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                <span class="text-xs">{{ $attendance->check_in_method->label() }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                <button wire:click="openClockForm({{ $attendance->employee_id }})" class="mr-2 text-blue-600 hover:text-blue-800">Ubah</button>
                                <button wire:click="deleteAttendance({{ $attendance->id }})" wire:confirm="Hapus data absensi ini?" class="text-red-600 hover:text-red-800">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada data absensi. Tekan "Generate Harian" untuk memproses karyawan yang belum tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $attendances->links() }}
        </div>
    @else
        {{-- Rekap bulanan --}}
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Karyawan</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Hadir</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Telat</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Alpha</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Izin/Cuti</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Libur</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Total Telat</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Total Lembur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($monthlyRecap as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                {{ $row['employee']->full_name }}
                                <span class="block text-xs text-gray-400">{{ $row['employee']->employee_number }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-green-700 font-medium">{{ $row['present'] }}</td>
                            <td class="px-4 py-3 text-right text-sm text-amber-700 font-medium">{{ $row['late'] }}</td>
                            <td class="px-4 py-3 text-right text-sm text-red-700 font-medium">{{ $row['absent'] }}</td>
                            <td class="px-4 py-3 text-right text-sm text-blue-700 font-medium">{{ $row['on_leave'] }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-600 font-medium">{{ $row['holiday'] }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-600">{{ $row['late_minutes'] }} mnt</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-600">{{ number_format($row['overtime_hours'], 2) }} jam</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada data karyawan aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if($showClockModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50" wire:click.self="$set('showClockModal', false)">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Catat Absensi Manual</h3>

                @if($errorMessage)
                    <div class="mb-4 rounded-lg bg-red-100 p-3 text-sm text-red-700">{{ $errorMessage }}</div>
                @endif

                <form wire:submit="saveClock" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Karyawan</label>
                        <select wire:model="clockEmployeeId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="">— Pilih Karyawan —</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }} ({{ $employee->employee_number }})</option>
                            @endforeach
                        </select>
                        @error('clockEmployeeId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Tanggal</label>
                        <input type="date" wire:model="clockDate" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        @error('clockDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Jam Masuk</label>
                            <input type="time" wire:model="clockInTime" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @error('clockInTime') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Jam Pulang</label>
                            <input type="time" wire:model="clockOutTime" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @error('clockOutTime') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Metode Pencatatan</label>
                        <select wire:model="clockMethod" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @foreach($methods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Catatan</label>
                        <input type="text" wire:model="clockNote" placeholder="Opsional" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showClockModal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
