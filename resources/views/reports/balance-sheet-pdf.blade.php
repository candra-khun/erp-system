<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Neraca</title>
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
        .muted { color: #888; font-size: 10px; }
    </style>
</head>
<body>
    <h1>NERACA</h1>
    <p class="subtitle">Posisi per {{ \Carbon\Carbon::parse($asOfDate)->format('d M Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Keterangan</th>
                <th class="text-right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            {{-- ASET --}}
            <tr class="section-header">
                <td colspan="2">ASET</td>
            </tr>
            @forelse($assets as $account)
                <tr>
                    <td class="indent">{{ $account['code'] }} — {{ $account['name'] }}</td>
                    <td class="text-right">{{ number_format($account['balance'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td class="indent muted" colspan="2">Belum ada akun aset bersaldo.</td>
                </tr>
            @endforelse
            <tr class="total-row" style="background-color: #f0f9ff;">
                <td>TOTAL ASET</td>
                <td class="text-right">{{ number_format($totalAssets, 2, ',', '.') }}</td>
            </tr>

            {{-- KEWAJIBAN --}}
            <tr class="section-header">
                <td colspan="2">KEWAJIBAN</td>
            </tr>
            @forelse($liabilities as $account)
                <tr>
                    <td class="indent">{{ $account['code'] }} — {{ $account['name'] }}</td>
                    <td class="text-right">{{ number_format($account['balance'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td class="indent muted" colspan="2">Belum ada kewajiban bersaldo.</td>
                </tr>
            @endforelse
            <tr class="font-bold">
                <td>Total Kewajiban</td>
                <td class="text-right">{{ number_format($totalLiabilities, 2, ',', '.') }}</td>
            </tr>

            {{-- EKUITAS --}}
            <tr class="section-header">
                <td colspan="2">EKUITAS</td>
            </tr>
            @foreach($equity as $account)
                <tr>
                    <td class="indent">{{ $account['code'] }} — {{ $account['name'] }}</td>
                    <td class="text-right">{{ number_format($account['balance'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="indent">Laba Ditahan (periode berjalan)</td>
                <td class="text-right">{{ number_format($retainedEarnings, 2, ',', '.') }}</td>
            </tr>
            <tr class="font-bold">
                <td>Total Ekuitas</td>
                <td class="text-right">{{ number_format($totalEquity, 2, ',', '.') }}</td>
            </tr>

            <tr class="total-row" style="background-color: #f8fafc;">
                <td>TOTAL KEWAJIBAN + EKUITAS</td>
                <td class="text-right">{{ number_format($totalLiabilitiesAndEquity, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top: 40px; font-size: 10px; color: #999; text-align: center;">
        Dicetak pada: {{ now()->format('d M Y H:i:s') }}
    </p>
</body>
</html>