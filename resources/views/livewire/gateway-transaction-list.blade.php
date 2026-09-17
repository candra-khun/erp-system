<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Transaksi Payment Gateway</h1>
        <p class="mt-1 text-sm text-gray-600">QRIS / virtual account / kartu — konfirmasi & settlement pembayaran.</p>
    </div>

    @if($errorMessage)
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    <div class="mb-4 flex flex-wrap gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nomor / referensi gateway..." class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:w-72">
        <select wire:model.live="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Status</option>
            <option value="pending">Pending</option>
            <option value="created">Charge Dibuat</option>
            <option value="settlement">Settlement</option>
            <option value="expire">Kedaluwarsa</option>
            <option value="cancel">Dibatalkan</option>
            <option value="refund">Refund</option>
            <option value="failed">Gagal</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Nomor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Provider</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Channel</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Nominal</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Settlement</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($transactions as $transaction)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                            {{ $transaction->transaction_number }}
                            <div class="text-xs text-gray-500">{{ $transaction->gateway_transaction_id ?? '-' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ ucfirst($transaction->provider) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $transaction->payment_channel ?? '-' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900">Rp {{ number_format((float) $transaction->amount, 0, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700">
                            {{ $transaction->settlement_amount ? 'Rp '.number_format((float) $transaction->settlement_amount, 0, ',', '.') : '-' }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @php
                                $badges = ['pending' => 'gray', 'created' => 'blue', 'paid' => 'yellow', 'settlement' => 'green', 'expire' => 'gray', 'cancel' => 'red', 'refund' => 'orange', 'deny' => 'red', 'failed' => 'red'];
                                $badge = $badges[$transaction->status] ?? 'gray';
                            @endphp
                            <span class="rounded-full bg-{{ $badge }}-100 px-2 py-0.5 text-xs font-medium text-{{ $badge }}-700">{{ ucfirst($transaction->status) }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            @if(! $transaction->isPaid())
                                <button wire:click="openConfirm({{ $transaction->id }})" class="mr-2 text-green-600 hover:text-green-800">Konfirmasi Bayar</button>
                                <button wire:click="cancelTransaction({{ $transaction->id }})" class="mr-2 text-red-600 hover:text-red-800">Batal</button>
                            @endif
                            <button wire:click="syncStatus({{ $transaction->id }})" class="text-blue-600 hover:text-blue-800">Sync</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada transaksi gateway.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $transactions->links() }}</div>

    @if($confirmId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4" wire:click.self="$set('confirmId', null)">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold text-gray-900">Konfirmasi Pembayaran</h2>

                <p class="mb-4 text-sm text-gray-600">
                    Tandai transaksi gateway telah diterima. Settlement akan tercatat (nominal dikurangi fee).
                </p>

                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Referensi Pembayaran (opsional)</label>
                    <input type="text" wire:model="paymentReference" placeholder="mis. QRIS-20260917-001" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    @error('paymentReference')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-3">
                    <button wire:click="$set('confirmId', null)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                    <button wire:click="confirmPayment" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Konfirmasi</button>
                </div>
            </div>
        </div>
    @endif
</div>
