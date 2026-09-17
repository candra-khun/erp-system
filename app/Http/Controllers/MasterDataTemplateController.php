<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh template CSV untuk import master data (PRD 4.1 Should Have).
 */
class MasterDataTemplateController extends Controller
{
    public function products(): StreamedResponse
    {
        return $this->download('products');
    }

    public function suppliers(): StreamedResponse
    {
        return $this->download('suppliers');
    }

    public function customers(): StreamedResponse
    {
        return $this->download('customers');
    }

    private function download(string $type): StreamedResponse
    {
        $headings = match ($type) {
            'suppliers' => ['kode', 'nama', 'kontak', 'telepon', 'email', 'alamat', 'kota', 'provinsi', 'kode_pos', 'termin_hari', 'aktif'],
            'customers' => ['kode', 'nama', 'tipe', 'kontak', 'telepon', 'email', 'alamat', 'kota', 'provinsi', 'kode_pos', 'limit_kredit', 'termin_hari', 'aktif'],
            default => ['sku', 'nama', 'kategori', 'satuan', 'barcode', 'harga_beli', 'harga_jual', 'stok_minimum', 'reorder_point', 'deskripsi', 'aktif'],
        };

        $sample = match ($type) {
            'suppliers' => ['SUP-001', 'PT Contoh Supplier', 'Budi', '021-123456', 'supplier@contoh.id', 'Jl. Contoh 1', 'Jakarta', 'DKI Jakarta', '12345', '30', '1'],
            'customers' => ['CUST-001', 'Toko Contoh', 'general', 'Siti', '08123456789', 'toko@contoh.id', 'Jl. Contoh 2', 'Bandung', 'Jawa Barat', '40111', '5000000', '14', '1'],
            default => ['PRD-001', 'Produk Contoh', 'Umum', 'Pcs', '1234567890123', '10000', '15000', '10', '5', 'Produk contoh', '1'],
        };

        $filename = 'template-import-'.rtrim($type, 's').'.csv';

        return response()->streamDownload(function () use ($headings, $sample): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, $headings);
            fputcsv($out, $sample);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
