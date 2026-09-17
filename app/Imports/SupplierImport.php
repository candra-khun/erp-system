<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import master supplier dari Excel/CSV (PRD 4.1 Should Have).
 * Upsert berdasarkan kode supplier.
 */
class SupplierImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
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
            $line = (int) $index + 2;
            $code = trim((string) ($row['kode'] ?? $row['code'] ?? ''));
            $name = trim((string) ($row['nama'] ?? $row['name'] ?? ''));

            if ($code === '' || $name === '') {
                $this->errors[] = "Baris {$line}: kolom Kode dan Nama wajib diisi.";

                continue;
            }

            try {
                $attributes = [
                    'name' => $name,
                    'contact_person' => $this->nullableString($row['kontak'] ?? $row['contact_person'] ?? null),
                    'phone' => $this->nullableString($row['telepon'] ?? $row['phone'] ?? null),
                    'email' => $this->nullableString($row['email'] ?? null),
                    'address' => $this->nullableString($row['alamat'] ?? $row['address'] ?? null),
                    'city' => $this->nullableString($row['kota'] ?? $row['city'] ?? null),
                    'province' => $this->nullableString($row['provinsi'] ?? $row['province'] ?? null),
                    'postal_code' => $this->nullableString($row['kode_pos'] ?? $row['postal_code'] ?? null),
                    'payment_terms_days' => (int) ($row['termin_hari'] ?? $row['payment_terms_days'] ?? 0),
                    'is_active' => $this->parseBool($row['aktif'] ?? $row['is_active'] ?? true),
                ];

                $existing = Supplier::withTrashed()->where('code', $code)->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->update($attributes);
                    $this->updated++;
                } else {
                    Supplier::create(['code' => $code] + $attributes);
                    $this->imported++;
                }
            } catch (\Throwable $e) {
                $this->errors[] = "Baris {$line}: {$e->getMessage()}";
            }
        }
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
