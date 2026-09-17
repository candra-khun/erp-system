<div class="space-y-4">
    @if(session()->has('success'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session()->has('error'))
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-900">Rekonsiliasi Bank</h2>
        <button wire:click="openForm" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">+ Sesi Rekonsiliasi</button>
    </div>

    @if($showForm)
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 space-y-3">
            <h3 class="font-semibold text-blue-900">Sesi Baru</h3>
            @if($errorMessage)
                <p class="text-sm text-red-600">{{ $errorMessage }}</p>
            @endif
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Gudang</label>
                    <select wire:model="warehouseId" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="">Semua gudang</option>
                        @foreach($this->warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Saldo Laporan Bank</label>
                    <input wire:model="bankStatementBalance" type="number" step="0.01" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    @error('bankStatementBalance')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Periode Mulai</label>
                    <input wire:model="periodStart" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    @error('periodStart')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Periode Akhir</label>
                    <input wire:model="periodEnd" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    @error('periodEnd')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-700">Baris Rekening Koran</h4>
                    <div class="flex items-center gap-2">
                        <input type="file" wire:model.change="statementFile" class="hidden" id="statement-file">
                        <button type="button" onclick="document.getElementById('statement-file').click()" class="rounded border border-gray-300 bg-white px-2 py-1 text-xs">⬆ Impor Excel/CSV</button>
                        <button type="button" wire:click="addLine" class="rounded border border-gray-300 bg-white px-2 py-1 text-xs">+ Baris</button>
                    </div>
                </div>
                <div wire:loading wire:target="statementFile" class="text-xs text-blue-600">Membaca file…</div>
                @error('lines')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @foreach($lines as $i => $line)
                    <div class="flex flex-wrap gap-2 items-end">
                        <input wire:model="lines.{{ $i }}.value_date" type="date" class="rounded-md border-gray-300 text-sm">
                        <input wire:model="lines.{{ $i }}.description" placeholder="Keterangan" class="flex-1 min-w-[10rem] rounded-md border-gray-300 text-sm">
                        <input wire:model="lines.{{ $i }}.amount" type="number" step="0.01" placeholder="Nominal" class="w-32 rounded-md border-gray-300 text-sm">
                        <button type="button" wire:click="removeLine({{ $i }})" class="text-xs text-red-600">Hapus</button>
                    </div>
                @endforeach
            </div>

            <div class="flex gap-2">
                <button wire:click="saveSession" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan & Cocokkan</button>
                <button wire:click="$set('showForm', false)" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
            </div>
        </div>
    @endif

    {{-- Detail sesi terbuka --}}
    @if($this->openSession)
        @php($br = $this->openSession)
        <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="font-semibold text-gray-900">{{ $br->reconciliation_number }}</h3>
                    <p class="text-xs text-gray-500">
                        Periode {{ $br->period_start->format('d/m/Y') }} — {{ $br->period_end->format('d/m/Y') }}
                        {{ $br->warehouse?->name ? '· '.$br->warehouse->name : '' }}
                    </p>
                </div>
                <div class="flex gap-2 text-sm">
                    @if($br->status === 'open')
                        <button wire:click="rematch({{ $br->id }})" class="rounded border border-gray-300 bg-white px-3 py-1.5">Cocokkan Ulang</button>
                        <button wire:click="completeSession({{ $br->id }})" wire:confirm="Selesaikan rekonsiliasi?" class="rounded bg-green-600 px-3 py-1.5 font-medium text-white">Selesaikan</button>
                        <button wire:click="cancelSession({{ $br->id }})" class="rounded border border-red-300 bg-white px-3 py-1.5 text-red-600">Batal</button>
                    @else
                        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $br->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $br->status }}</span>
                    @endif
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div class="rounded bg-gray-50 p-3"><div class="text-xs text-gray-500">Saldo Bank</div><div class="font-semibold">Rp {{ number_format((float) $br->bank_statement_balance, 0, ',', '.') }}</div></div>
                <div class="rounded bg-gray-50 p-3"><div class="text-xs text-gray-500">Saldo Buku (cocok)</div><div class="font-semibold">Rp {{ number_format((float) $br->book_balance, 0, ',', '.') }}</div></div>
                <div class="rounded {{ (float) $br->difference == 0 ? 'bg-green-50' : 'bg-yellow-50' }} p-3"><div class="text-xs text-gray-500">Selisih</div><div class="font-semibold">Rp {{ number_format((float) $br->difference, 0, ',', '.') }}</div></div>
                <div class="rounded bg-gray-50 p-3"><div class="text-xs text-gray-500">Belum Cocok</div><div class="font-semibold">{{ $br->statementLines->where('is_matched', false)->count() }} baris</div></div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2">Tanggal</th>
                            <th class="px-3 py-2">Keterangan</th>
                            <th class="px-3 py-2">Nominal</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Transaksi Cocok</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($br->statementLines as $line)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2">{{ $line->value_date->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">{{ $line->description }}</td>
                                <td class="px-3 py-2 {{ (float) $line->amount >= 0 ? 'text-green-700' : 'text-red-700' }}">Rp {{ number_format((float) $line->amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-2">
                                    @if($line->is_matched)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">Cocok</span>
                                    @else
                                        <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">Belum</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs">
                                    @if($line->matchedTransaction)
                                        {{ $line->matchedTransaction->transaction_date->format('d/m/Y') }} — Rp {{ number_format((float) $line->matchedTransaction->amount, 0, ',', '.') }}
                                        @if($br->status === 'open')
                                            <button wire:click="setLineMatch({{ $line->id }}, null)" class="text-red-600">lepas</button>
                                        @endif
                                    @elseif($br->status === 'open')
                                        <select wire:change="setLineMatch({{ $line->id }}, $event.target.value || null)" class="rounded-md border-gray-300 text-xs">
                                            <option value="">— pilih —</option>
                                            @foreach($this->candidatesFor($line) as $ct)
                                                <option value="{{ $ct->id }}">{{ $ct->transaction_date->format('d/m/Y') }} · Rp {{ number_format((float) $ct->amount, 0, ',', '.') }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Daftar sesi --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-3 py-2">Nomor</th>
                    <th class="px-3 py-2">Periode</th>
                    <th class="px-3 py-2">Saldo Bank</th>
                    <th class="px-3 py-2">Selisih</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->sessions as $s)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium">{{ $s->reconciliation_number }}</td>
                        <td class="px-3 py-2 text-xs">{{ $s->period_start->format('d/m/Y') }} — {{ $s->period_end->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">Rp {{ number_format((float) $s->bank_statement_balance, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 {{ (float) $s->difference == 0 ? '' : 'text-yellow-700' }}">Rp {{ number_format((float) $s->difference, 0, ',', '.') }}</td>
                        <td class="px-3 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ ['open' => 'bg-yellow-100 text-yellow-800', 'completed' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-red-100 text-red-800'][$s->status] }}">{{ $s->status }}</span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <button wire:click="$set('openSessionId', {{ $s->id }})" class="text-blue-600 text-xs font-medium">Buka</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">Belum ada sesi rekonsiliasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->sessions->links() }}
</div>
