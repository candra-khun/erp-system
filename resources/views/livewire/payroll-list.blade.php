<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Penggajian</h1>
        <button wire:click="$set('showForm', true)" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            + Buat Periode Gaji
        </button>
    </div>

    @if($errorMessage)
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    <div class="mb-4 flex flex-wrap gap-3">
        <select wire:model.live="warehouseId" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Cabang</option>
            @foreach($warehouseOptions as $w)
                <option value="{{ $w->id }}">{{ $w->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Periode</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Nomor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Cabang</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Karyawan</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Total Gaji</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($periods as $period)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ $period->label }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $period->period_number }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $period->warehouse?->name ?? 'Semua' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900">{{ $period->payrolls->count() }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900">
                            Rp {{ number_format((float) $period->payrolls->sum('net_salary'), 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @php
                                $badges = ['draft' => 'gray', 'generated' => 'blue', 'approved' => 'yellow', 'paid' => 'green', 'cancelled' => 'red'];
                            @endphp
                            <span class="rounded-full bg-{{ $badges[$period->status->value] ?? 'gray' }}-100 px-2 py-0.5 text-xs font-medium text-{{ $badges[$period->status->value] ?? 'gray' }}-700">
                                {{ $period->status->label }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button wire:click="toggleDetails({{ $period->id }})" class="mr-2 text-gray-600 hover:text-gray-800">Detail</button>
                            @if($period->status->value === 'draft')
                                <button wire:click="generate({{ $period->id }})" class="mr-2 text-blue-600 hover:text-blue-800">Generate</button>
                            @endif
                            @if($period->status->value === 'generated')
                                <button wire:click="approve({{ $period->id }})" class="mr-2 text-green-600 hover:text-green-800">Setujui</button>
                            @endif
                        </td>
                    </tr>

                    @if($openPeriodId === $period->id)
                        <tr class="bg-gray-50">
                            <td colspan="7" class="px-4 py-4">
                                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-600">Karyawan</th>
                                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600">Gaji Pokok</th>
                                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600">Tunjangan</th>
                                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600">Potongan</th>
                                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600">Gaji Bersih</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-600">Status</th>
                                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            @forelse($period->payrolls as $payroll)
                                                <tr>
                                                    <td class="whitespace-nowrap px-3 py-2 text-sm font-medium text-gray-900">{{ $payroll->employee?->full_name }}</td>
                                                    <td class="px-3 py-2 text-right text-sm text-gray-700">Rp {{ number_format((float) $payroll->basic_salary, 0, ',', '.') }}</td>
                                                    <td class="px-3 py-2 text-right text-sm text-gray-700">Rp {{ number_format((float) $payroll->total_earnings, 0, ',', '.') }}</td>
                                                    <td class="px-3 py-2 text-right text-sm text-gray-700">Rp {{ number_format((float) $payroll->total_deductions + (float) $payroll->tax_amount, 0, ',', '.') }}</td>
                                                    <td class="px-3 py-2 text-right text-sm font-semibold text-gray-900">Rp {{ number_format((float) $payroll->net_salary, 0, ',', '.') }}</td>
                                                    <td class="px-3 py-2 text-sm text-gray-600">{{ $payroll->status->label }}</td>
                                                    <td class="px-3 py-2 text-right text-sm">
                                                        @if($payroll->status->value === 'approved')
                                                            <button wire:click="pay({{ $payroll->id }})" class="text-green-600 hover:text-green-800">Bayar</button>
                                                        @else
                                                            <span class="text-gray-400">-</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="7" class="px-3 py-4 text-center text-sm text-gray-500">Belum ada payroll. Generate periode terlebih dahulu.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada periode penggajian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $periods->links() }}</div>

    @if($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4" wire:click.self="$set('showForm', false)">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-gray-900">Buat Periode Penggajian</h2>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tahun <span class="text-red-500">*</span></label>
                        <input type="number" wire:model="periodYear" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('periodYear')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Bulan <span class="text-red-500">*</span></label>
                        <select wire:model="periodMonth" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}">{{ DateTime::createFromFormat('!m', (string) $m)->format('F') }}</option>
                            @endfor
                        </select>
                        @error('periodMonth')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-span-2">
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
                        <label class="mb-1 block text-sm font-medium text-gray-700">Hari Kerja</label>
                        <input type="number" step="0.5" wire:model="workingDays" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('workingDays')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Jam Lembur</label>
                        <input type="number" step="0.5" wire:model="overtimeHours" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('overtimeHours')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tarif Lembur per Jam (Rp)</label>
                        <input type="number" step="0.01" wire:model="overtimeRate" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @error('overtimeRate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="$set('showForm', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                    <button wire:click="createPeriod" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Buat Periode</button>
                </div>
            </div>
        </div>
    @endif
</div>
