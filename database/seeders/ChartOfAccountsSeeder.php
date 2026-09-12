<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets (1xxx)
            ['code' => '1000', 'name' => 'Aset Lancar', 'type' => 'asset'],
            ['code' => '1100', 'name' => 'Kas & Setara Kas', 'type' => 'asset', 'parent_code' => '1000'],
            ['code' => '1110', 'name' => 'Kas Toko', 'type' => 'asset', 'parent_code' => '1100'],
            ['code' => '1120', 'name' => 'Bank BCA', 'type' => 'asset', 'parent_code' => '1100'],
            ['code' => '1130', 'name' => 'Bank Mandiri', 'type' => 'asset', 'parent_code' => '1100'],
            ['code' => '1200', 'name' => 'Piutang Usaha', 'type' => 'asset', 'parent_code' => '1000'],
            ['code' => '1210', 'name' => 'Piutang Pelanggan', 'type' => 'asset', 'parent_code' => '1200'],
            ['code' => '1300', 'name' => 'Persediaan Barang', 'type' => 'asset', 'parent_code' => '1000'],
            ['code' => '1310', 'name' => 'Stok Gudang Utama', 'type' => 'asset', 'parent_code' => '1300'],

            // Liabilities (2xxx)
            ['code' => '2000', 'name' => 'Kewajiban Lancar', 'type' => 'liability'],
            ['code' => '2100', 'name' => 'Hutang Usaha', 'type' => 'liability', 'parent_code' => '2000'],
            ['code' => '2110', 'name' => 'Hutang Supplier', 'type' => 'liability', 'parent_code' => '2100'],
            ['code' => '2200', 'name' => 'Hutang Pajak', 'type' => 'liability', 'parent_code' => '2000'],
            ['code' => '2210', 'name' => 'PPN Keluaran', 'type' => 'liability', 'parent_code' => '2200'],

            // Equity (3xxx)
            ['code' => '3000', 'name' => 'Modal', 'type' => 'equity'],
            ['code' => '3100', 'name' => 'Modal Disetor', 'type' => 'equity', 'parent_code' => '3000'],
            ['code' => '3200', 'name' => 'Laba Ditahan', 'type' => 'equity', 'parent_code' => '3000'],

            // Revenue (4xxx)
            ['code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue'],
            ['code' => '4100', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'parent_code' => '4000'],
            ['code' => '4110', 'name' => 'Penjualan Tunai', 'type' => 'revenue', 'parent_code' => '4100'],
            ['code' => '4120', 'name' => 'Penjualan Kredit', 'type' => 'revenue', 'parent_code' => '4100'],
            ['code' => '4200', 'name' => 'Retur Penjualan', 'type' => 'revenue', 'parent_code' => '4000'],
            ['code' => '4300', 'name' => 'Diskon Penjualan', 'type' => 'revenue', 'parent_code' => '4000'],

            // Expenses (5xxx)
            ['code' => '5000', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense'],
            ['code' => '5100', 'name' => 'HPP Barang Dagang', 'type' => 'expense', 'parent_code' => '5000'],
            ['code' => '5200', 'name' => 'Biaya Operasional', 'type' => 'expense'],
            ['code' => '5210', 'name' => 'Biaya Gaji', 'type' => 'expense', 'parent_code' => '5200'],
            ['code' => '5220', 'name' => 'Biaya Sewa', 'type' => 'expense', 'parent_code' => '5200'],
            ['code' => '5230', 'name' => 'Biaya Listrik & Air', 'type' => 'expense', 'parent_code' => '5200'],
            ['code' => '5240', 'name' => 'Biaya Transportasi', 'type' => 'expense', 'parent_code' => '5200'],
            ['code' => '5300', 'name' => 'Retur Pembelian', 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            $parentId = null;
            if (isset($account['parent_code'])) {
                $parentId = Account::where('code', $account['parent_code'])->value('id');
            }

            Account::updateOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'parent_id' => $parentId,
                    'is_active' => true,
                ]
            );
        }
    }
}
