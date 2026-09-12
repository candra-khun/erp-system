<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportTableExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
        private readonly string $title = 'Laporan',
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return substr($this->title, 0, 31);
    }
}
