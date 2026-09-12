<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Laba Rugi</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #333; }
        h1 { text-align: center; font-size: 18px; margin-bottom: 4px; }
        .subtitle { text-align: center; font-size: 12px; color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .section-header { background-color: #e8e8e8; font-weight: bold; }
        .total-row { border-top: 2px solid #333; font-weight: bold; font-size: 14px; }
        .indent { padding-left: 32px; }
    </style>
</head>
<body>
    <h1>LAPORAN LABA RUGI</h1>
    <p class="subtitle">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Keterangan</th>
                <th class="text-right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr class="section-header">
                <td colspan="2">PENDAPATAN</td>
            </tr>
            <tr>
                <td class="indent">Penjualan</td>
                <td class="text-right">{{ number_format($totalRevenue, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="font-bold">Total Pendapatan</td>
                <td class="text-right font-bold">{{ number_format($totalRevenue, 2, ',', '.') }}</td>
            </tr>

            <tr class="section-header">
                <td colspan="2">HARGA POKOK PENJUALAN (HPP)</td>
            </tr>
            <tr>
                <td class="indent">Pembelian / Penerimaan Barang</td>
                <td class="text-right">{{ number_format($totalCogs, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="font-bold">Total HPP</td>
                <td class="text-right font-bold">{{ number_format($totalCogs, 2, ',', '.') }}</td>
            </tr>

            <tr class="total-row" style="background-color: #f0f9ff;">
                <td>LABA KOTOR</td>
                <td class="text-right">{{ number_format($grossProfit, 2, ',', '.') }}</td>
            </tr>

            <tr class="section-header">
                <td colspan="2">BEBAN OPERASIONAL</td>
            </tr>
            <tr>
                <td class="indent">Pengeluaran Kas</td>
                <td class="text-right">{{ number_format($totalExpenses, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="font-bold">Total Beban</td>
                <td class="text-right font-bold">{{ number_format($totalExpenses, 2, ',', '.') }}</td>
            </tr>

            <tr class="total-row" style="background-color: {{ $netProfit >= 0 ? '#f0fdf4' : '#fef2f2' }};">
                <td>LABA / (RUGI) BERSIH</td>
                <td class="text-right">{{ number_format($netProfit, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top: 40px; font-size: 10px; color: #999; text-align: center;">
        Dicetak pada: {{ now()->format('d M Y H:i:s') }}
    </p>
</body>
</html>
