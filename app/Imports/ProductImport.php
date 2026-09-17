<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import master produk dari Excel/CSV (PRD 4.1 Should Have).
 * Baris dengan SKU yang sudah ada akan diperbarui (upsert by SKU).
 */
class ProductImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public int $updated = 0;

    /** @var list<string> */
    public array $errors = [];

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $line = (int) $index + 2; // baris 1 = header
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? $row['nama'] ?? ''));

            if ($sku === '' || $name === '') {
                $this->errors[] = "Baris {$line}: kolom SKU dan Nama wajib diisi.";

                continue;
            }

            try {
                $attributes = [
                    'name' => $name,
                    'product_category_id' => $this->resolveCategory((string) ($row['kategori'] ?? $row['category'] ?? '')),
                    'base_unit_id' => $this->resolveUnit((string) ($row['satuan'] ?? $row['unit'] ?? '')),
                    'barcode' => $this->nullableString($row['barcode'] ?? null),
                    'description' => $this->nullableString($row['deskripsi'] ?? $row['description'] ?? null),
                    'purchase_price' => (float) ($row['harga_beli'] ?? $row['purchase_price'] ?? 0),
                    'selling_price' => (float) ($row['harga_jual'] ?? $row['selling_price'] ?? 0),
                    'min_stock' => (float) ($row['stok_minimum'] ?? $row['min_stock'] ?? 0),
                    'reorder_point' => (float) ($row['reorder_point'] ?? 0),
                    'is_active' => $this->parseBool($row['aktif'] ?? $row['is_active'] ?? true),
                ];

                $existing = Product::withTrashed()->where('sku', $sku)->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->update($attributes);
                    $this->updated++;
                } else {
                    Product::create(['sku' => $sku] + $attributes);
                    $this->imported++;
                }
            } catch (\Throwable $e) {
                $this->errors[] = "Baris {$line}: {$e->getMessage()}";
            }
        }
    }

    private function resolveCategory(string $name): int
    {
        $name = trim($name) !== '' ? trim($name) : 'Umum';

        return ProductCategory::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true],
        )->id;
    }

    private function resolveUnit(string $name): int
    {
        $name = trim($name) !== '' ? trim($name) : 'Pcs';

        return ProductUnit::firstOrCreate(
            ['name' => $name],
            ['symbol' => Str::limit($name, 10, ''), 'is_base' => true, 'conversion_factor' => 1],
        )->id;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return ! in_array($normalized, ['0', 'false', 'tidak', 'no', ''], true);
    }
}
