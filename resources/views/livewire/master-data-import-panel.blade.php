<div class="rounded-lg border border-gray-200 bg-white p-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Import Master Data</h3>
            <p class="text-xs text-gray-500">Unggah berkas Excel (.xlsx/.xls) atau CSV. Data dengan kode/SKU sama akan diperbarui.</p>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="downloadTemplate" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Unduh Template</button>
            <button type="button" wire:click="togglePanel" class="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-900">{{ $showPanel ? 'Tutup' : 'Import' }}</button>
        </div>
    </div>

    @if($showPanel)
        <div class="space-y-3 border-t border-gray-100 pt-3">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[16rem]">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Berkas</label>
                    <input type="file" wire:model="file" accept=".csv,.txt,.xlsx,.xls"
                           class="block w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <div wire:loading wire:target="file" class="text-xs text-blue-600 mt-1">Mengunggah…</div>
                    @error('file') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Proses Import
                </button>
            </div>

            @if($result)
                <div class="rounded-md border border-gray-200 bg-gray-50 p-3 text-xs">
                    <p class="font-medium text-gray-800">Hasil: {{ $result['imported'] }} baru, {{ $result['updated'] }} diperbarui, {{ count($result['errors']) }} gagal.</p>
                    @if(count($result['errors']) > 0)
                        <ul class="mt-2 list-disc list-inside space-y-0.5 text-red-600">
                            @foreach(array_slice($result['errors'], 0, 20) as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                        @if(count($result['errors']) > 20)
                            <p class="mt-1 text-gray-500">…dan {{ count($result['errors']) - 20 }} kesalahan lain.</p>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>