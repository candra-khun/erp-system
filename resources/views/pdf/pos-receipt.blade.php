<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Struk POS - {{ $transaction->transaction_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 11px; width: 72mm; padding: 2mm; line-height: 1.4; }
        .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 4px; margin-bottom: 6px; }
        .store-name { font-size: 16px; font-weight: bold; margin-bottom: 2px; }
        .store-info { font-size: 9px; }
        .receipt-info { margin-bottom: 6px; font-size: 10px; }
        .receipt-info table { width: 100%; }
        .receipt-info td { padding: 1px 0; }
        .separator { border-top: 1px dashed #000; margin: 4px 0; }
        .items-table { width: 100%; margin-bottom: 4px; }
        .items-table th { text-align: left; font-size: 10px; border-bottom: 1px solid #000; padding: 2px 0; }
        .items-table td { padding: 2px 0; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals-table { width: 100%; margin-bottom: 4px; }
        .totals-table td { padding: 2px 0; }
        .grand-total { font-size: 14px; font-weight: bold; }
        .footer { text-align: center; border-top: 1px dashed #000; padding-top: 6px; margin-top: 6px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="store-name">{{ config('app.name', 'TOKO KAMI') }}</div>
        <div class="store-info">
            {{ config('app.store_address', 'Jl. Contoh No. 123') }}<br>
            Telp: {{ config('app.store_phone', '021-1234567') }}
        </div>
    </div>

    <div class="receipt-info">
        <table>
            <tr><td>No. Transaksi</td><td>: {{ $transaction->transaction_number }}</td></tr>
            <tr><td>Tanggal</td><td>: {{ $transaction->created_at->format('d/m/Y H:i') }}</td></tr>
            <tr><td>Kasir</td><td>: {{ $transaction->cashier->name ?? '-' }}</td></tr>
            @if($transaction->customer)
            <tr><td>Customer</td><td>: {{ $transaction->customer->name }}</td></tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Harga</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $item)
            <tr>
                <td colspan="4">{{ $item->product->name ?? 'Product #' . $item->product_id }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="text-center">{{ number_format((float) $item->quantity, 0) }}</td>
                <td class="text-right">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="separator"></div>

    <table class="totals-table">
        <tr><td>Subtotal</td><td class="text-right">{{ number_format((float) $transaction->subtotal, 0, ',', '.') }}</td></tr>
        @if((float) $transaction->discount_amount > 0)
        <tr><td>Diskon</td><td class="text-right">-{{ number_format((float) $transaction->discount_amount, 0, ',', '.') }}</td></tr>
        @endif
        @if((float) $transaction->tax_amount > 0)
        <tr><td>Pajak</td><td class="text-right">{{ number_format((float) $transaction->tax_amount, 0, ',', '.') }}</td></tr>
        @endif
        <tr class="grand-total"><td>TOTAL</td><td class="text-right">{{ number_format((float) $transaction->total_amount, 0, ',', '.') }}</td></tr>
        <tr><td>Bayar ({{ ucfirst($transaction->payment_method ?? 'Cash') }})</td><td class="text-right">{{ number_format((float) $transaction->paid_amount, 0, ',', '.') }}</td></tr>
        <tr><td>Kembalian</td><td class="text-right">{{ number_format((float) $transaction->change_amount, 0, ',', '.') }}</td></tr>
    </table>

    <div class="footer">
        Terima kasih atas kunjungan Anda!<br>
        Barang yang sudah dibeli tidak dapat ditukar/dikembalikan
    </div>
</body>
</html>