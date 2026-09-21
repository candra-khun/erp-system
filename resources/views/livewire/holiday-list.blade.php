<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Kalender Hari Libur</h1>
        <button wire:click="createHoliday" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            + Tambah Hari Libur
        </button>
    </div>

    @if($errorMessage)
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama libur..." class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:w-96">
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Nama</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Tanggal / Hari</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Tipe</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Berulang Tahunan</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($holidays as $holiday)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ $holiday->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                            @if($holiday->type->value === 'weekly_off')
                                Setiap {{ $dayNames[$holiday->weekly_day_of_week] ?? '-' }}
                            @else
                                {{ \Illuminate\Support\Carbon::parse($holiday->holiday_date)->translatedFormat('d M Y') }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @php($color = match($holiday->type->value) {
                                'national' => 'red',
                                'religious' => 'purple',
                                'company' => 'blue',
                                default => 'gray',
                            })
                            <span class="rounded-full bg-{{ $color }}-100 px-2 py-0.5 text-xs font-medium text-{{ $color }}-700">{{ $holiday->type->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            @if($holiday->is_recurring_annual)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Ya</span>
                            @else
                                <span class="text-gray-400">Tidak</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button wire:click="editHoliday({{ $holiday->id }})" class="mr-2 text-blue-600 hover:text-blue-800">Edit</button>
                            <button wire:click="deleteHoliday({{ $holiday->id }})" wire:confirm="Hapus hari libur ini?" class="text-red-600 hover:text-red-800">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada hari libur. Tambahkan libur nasional dan libur mingguan (weekly off).</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $holidays->links() }}
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50" wire:click.self="$set('showModal', false)">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ $isEdit ? 'Edit Hari Libur' : 'Tambah Hari Libur' }}</h3>

                <form wire:submit="saveHoliday" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Nama Hari Libur</label>
                        <input type="text" wire:model="name" placeholder="mis. Tahun Baru Masehi"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Tipe</label>
                        <select wire:model.live="type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @foreach($types as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($type === 'weekly_off')
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Hari Libur Mingguan</label>
                            <select wire:model="weeklyDayOfWeek" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                <option value="">— Pilih Hari —</option>
                                @foreach($dayNames as $key => $dayName)
                                    <option value="{{ $key }}">{{ $dayName }}</option>
                                @endforeach
                            </select>
                            @error('weeklyDayOfWeek') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">Tanggal</label>
                            <input type="date" wire:model="holidayDate" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @error('holidayDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="isRecurringAnnual" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            Berulang setiap tahun (tanggal &amp; bulan sama)
                        </label>
                    @endif

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Catatan</label>
                        <input type="text" wire:model="notes" placeholder="Opsional" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
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
