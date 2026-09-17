<?php

declare(strict_types=1);

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import baris rekening koran dari Excel/CSV (PRD 4.5 — Rekonsiliasi Bank, Could Have).
 *
 * Header yang dikenali (case-insensitive, spasi/underscore setara):
 *   tanggal / date            => value_date (Y-m-d)
 *   keterangan / description  => description
 *   nominal / amount          => amount (bisa positif/negatif, pemisah ribuan diterima)
 *   referensi / reference     => bank_reference
 *
 * Baris dengan tanggal atau nominal tidak valid dilewati dan dicatat di $errors.
 */
class BankStatementImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var list<string> */
    public array $errors = [];

    /** @var list<array{value_date: string, description: string, amount: float, bank_reference: ?string}> */
    public array $lines = [];

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     * @return list<array{value_date: string, description: string, amount: float, bank_reference: ?string}>
     */
    public function collection(Collection $rows): void
    {
        $lines = [];

        foreach ($rows as $index => $row) {
            $line = (int) $index + 2;

            $date = $this->parseDate($row, $line);
            $amount = $this->parseAmount($row, $line);

            // Lewati baris yang tanggalnya tidak terbaca (bisa jadi hanya sekedar catatan).
            if ($date === null) {
                if ($this->hasAnyValue($row)) {
                    $this->errors[] = "Baris {$line}: format tanggal tidak dikenali.";
                }

                continue;
            }

            if ($amount === null) {
                $this->errors[] = "Baris {$line}: nominal tidak valid.";

                continue;
            }

            $lines[] = [
                'value_date' => $date,
                'description' => $this->stringField($row, ['keterangan', 'description', 'deskripsi', 'narration']),
                'amount' => $amount,
                'bank_reference' => $this->nullableStringField($row, ['referensi', 'reference', 'ref', 'no_referensi']),
            ];

            $this->imported++;
        }

        $this->lines = $lines;
    }

    private function hasAnyValue(Collection $row): bool
    {
        return $row->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty();
    }

    private function parseDate(Collection $row, int $line): ?string
    {
        $raw = $this->nullableStringField($row, ['tanggal', 'date', 'tgl', 'value_date', 'tanggal_valuta']);

        if ($raw === null) {
            return null;
        }

        // Excel serial date.
        if (is_numeric($raw)) {
            try {
                return Date::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'm/d/Y', 'j-n-Y', 'j/n/Y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $raw);

            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }

        try {
            return (new \DateTimeImmutable($raw))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAmount(Collection $row, ?int $line = null): ?float
    {
        $raw = $this->nullableStringField($row, ['nominal', 'amount', 'jumlah', 'nilai', 'debit_kredit', 'mutasi']);

        if ($raw === null) {
            return null;
        }

        // Hapus pemisah ribuan & spasi; ganti koma desimal.
        $cleaned = str_replace(['.', ' '], '', $raw);
        $cleaned = str_replace(',', '.', $cleaned);
        $cleaned = str_replace(['(', ')'], ['-', ''], $cleaned);
        $cleaned = trim($cleaned, " \t\n\r");

        if (! is_numeric($cleaned)) {
            return null;
        }

        return (float) $cleaned;
    }

    /**
     * Ambil nilai kolom pertama yang ada dari daftar kemungkinan nama header.
     */
    private function rawField(Collection $row, array $keys): ?string
    {
        $normalized = $row->mapWithKeys(fn ($value, $key) => [
            $this->normalizeHeader((string) $key) => $value,
        ]);

        foreach ($keys as $key) {
            $normalizedKey = $this->normalizeHeader($key);

            if ($normalized->has($normalizedKey)) {
                $value = $normalized->get($normalizedKey);

                return $value === null ? null : trim((string) $value);
            }
        }

        return null;
    }

    private function stringField(Collection $row, array $keys): string
    {
        return (string) $this->rawField($row, $keys);
    }

    private function nullableStringField(Collection $row, array $keys): ?string
    {
        $value = $this->rawField($row, $keys);

        return $value === null || $value === '' ? null : $value;
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(str_replace([' ', '_', '-'], '', trim($header)));
    }
}
