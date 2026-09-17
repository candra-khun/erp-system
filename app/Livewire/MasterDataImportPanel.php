<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Imports\CustomerImport;
use App\Imports\ProductImport;
use App\Imports\SupplierImport;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Panel import master data (produk / supplier / pelanggan) via Excel atau CSV.
 * Dirender inline di halaman master data masing-masing (PRD 4.1 Should Have).
 */
class MasterDataImportPanel extends Component
{
    use WithFileUploads;

    public string $type = 'products';

    public $file = null;

    public bool $showPanel = false;

    /** @var array{imported: int, updated: int, errors: list<string>}|null */
    public ?array $result = null;

    public function togglePanel(): void
    {
        $this->showPanel = ! $this->showPanel;
        $this->resetValidation();
        $this->result = null;
    }

    public function downloadTemplate(): void
    {
        $routeName = match ($this->type) {
            'suppliers' => 'master-data.import-template.suppliers',
            'customers' => 'master-data.import-template.customers',
            default => 'master-data.import-template.products',
        };

        $this->redirectRoute($routeName, navigate: false);
    }

    public function import(): void
    {
        $this->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ], [], ['file' => 'berkas']);

        $this->result = null;

        /** @var ProductImport|SupplierImport|CustomerImport $import */
        $import = match ($this->type) {
            'suppliers' => new SupplierImport,
            'customers' => new CustomerImport,
            default => new ProductImport,
        };

        $extension = strtolower($this->file->getClientOriginalExtension() ?: 'csv');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'master-import-').'.'.$extension;
        copy($this->file->getRealPath(), $temporaryPath);

        try {
            Excel::import($import, $temporaryPath);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        $this->result = [
            'imported' => $import->imported,
            'updated' => $import->updated,
            'errors' => $import->errors,
        ];

        $this->file = null;

        $total = $import->imported + $import->updated;

        if ($total > 0) {
            session()->flash('success', "Import selesai: {$import->imported} data baru, {$import->updated} data diperbarui.");
        } elseif ($import->errors !== []) {
            session()->flash('error', 'Import gagal. Periksa detail kesalahan di bawah.');
        }
    }

    public function render(): View
    {
        return view('livewire.master-data-import-panel');
    }
}
