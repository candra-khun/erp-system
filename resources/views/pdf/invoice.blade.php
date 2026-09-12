<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice - {{ $salesOrder->so_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; line-height: 1.5; }
        .page { padding: 20mm 15mm; }
        .header { display: table; width: 100%; margin-bottom: 30px; }
        .header-left { display: table-cell; width: 60%; vertical-align: top; }
        .header-right { display: table-cell; width: 40%; text-align: right; vertical-align: top; }
        .company-name { font-size: 22px; font-weight: bold; color: #1a1a2e; margin-bottom: 4px; }
        .company-info { font-size: 10px; color: #666; line-height: 1.6; }
        .invoice-title { font-size: 28px; font-weight: bold; color: #1a1a2e; margin-bottom: 5px; }
        .invoice-number { font-size: 12px; color: #555; margin-bottom: 3px; }
        .info-section { display: table; width: 100%; margin-bottom: 25px; }
        .info-left { display: table-cell; width: 50%; vertical-align: top; }
        .info-right { display: table-cell; width: 50%; vertical-align: top; text-align: right; }
        .info-label { font-size: 10px; color: #888; text-transform: uppercase; font-weight: bold; margin-bottom: 4px; }
        .info-value { font-size: 12px; color: #333; margin-bottom: 2px; }
        .customer-name { font-weight: bold; font-size: 13px; }
        table.items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.items-table thead th { background-color: #1a1a2e; color: #fff; padding: 8px 10px; font-size: 11px; text-align: left; font-weight: 600; }
        table.items-table thead th.text-right { text-align: right; }
        table.items-table thead th.text-center { text-align: center; }
        table.items-table tbody td { padding: 8px 10px; border-bottom: 1px solid #e0e0e0; font-size: 11px; }
        table.items-table tbody td.text-right { text-align: right; }
        table.items-table tbody td.text-center { text-align: center; }
        table.items-table tbody tr:nth-child(even) { background-color: #f9f9f9; }
        .totals-section { display: table; width: 100%; margin-bottom: 25px; }
        .totals-spacer { display: table-cell; width: 55%; }
        .totals-box { display: table-cell; width: 45%; }
        table.totals-table { width: 100%; border-collapse: collapse; }
        table.totals-table td { padding: 6px 10px; font-size: 12px; }
        table.totals-table td.label { text-align: left; color: #555; }
        table.totals-table td.value { text-align: right; font-weight: 600; }
        table.totals-table tr.grand-total td { font-size: 16px; font-weight: bold; color: #1a1a2e; border-top: 2px solid #1a1a2e; padding-top: 10px; }
        .payment-terms { background-color: #f5f5f5; padding: 12px 15px; border-left: 4px solid #1a1a2e; margin-bottom: 25px; font-size: 11px; }
        .payment-terms strong { display: block; margin-bottom: 3px; font-size: 12px; }
        .notes-section { margin-bottom: 25px; font-size: 11px; color: #666; }
        .notes-section strong { display: block; margin-bottom: 3px; color: #333; }
        .footer { border-top: 1px solid #ddd; padding-top: 10px; text-align: center; font-size: 10px; color: #888; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .status-draft { background-color: #f0f0f0; color: #666; }
        .status-confirmed { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="page">
        {{-- Header --}}
        <div class="header">
            <div class="header-left">
                <div class="company-name">{{ config('app.name', 'PT. PERUSAHAAN KAMI') }}</div>
                <div class="company-info">
                    {{ config('app.company_address', 'Jl. Sudirman No. 1, Jakarta Pusat 10220') }}<br>
                    Telp: {{ config('app.company_phone', '021-1234567') }} | Email: {{ config('app.company_email', 'info@perusahaan.co.id') }}<br>
                    NPWP: {{ config('app.company_npwp', '01.234.567.8-901.000') }}
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number"><strong>No:</strong> {{ $salesOrder->so_number }}</div>
                <div class="invoice-number"><strong>Tanggal:</strong> {{ $salesOrder->order_date->format('d F Y') }}</div>
                @if($salesOrder->delivery_date)
                <div class="invoice-number"><strong>Jatuh Tempo:</strong> {{ $salesOrder->delivery_date->format('d F Y') }}</div>
                @endif
                <div style="margin-top: 5px;">
                    @php
                        $statusClass = 'status-' . strtolower($salesOrder->status);
                    @endphp
                    <span class="status-badge {{ $statusClass }}">{{ ucfirst($salesOrder->status) }}</span>
                </div>
            </div>
        </div>

        {{-- Customer & Order Info --}}
        <div class="info-section">
            <div class="info-left">
                <div class="info-label">Kepada:</div>
                @if($salesOrder->customer)
                <div class="customer-name">{{ $salesOrder->customer->name }}</div>
                <div class="info-value">{{ $salesOrder->customer->address ?? '' }}</div>
                <div class="info-value">
                    {{ trim(($salesOrder->customer->city ?? '') . ' ' . ($salesOrder->customer->postal_code ?? '')) }}
                </div>
                <div class="info-value">{{ $salesOrder->customer->province ?? '' }}</div>
                @if($salesOrder->customer->phone)
                <div class="info-value">Telp: {{ $salesOrder->customer->phone }}</div>
                @endif
                @if($salesOrder->customer->email)
                <div class="info-value">Email: {{ $salesOrder->customer->email }}</div>
                @endif
                @else
                <div class="customer-name">Pelanggan Umum</div>
                @endif
            </div>
            <div class="info-right">
                <div class="info-label">Detail Pesanan</div>
                <div class="info-value"><strong>Gudang:</strong> {{ $salesOrder->warehouse->name ?? '-' }}</div>
                <div class="info-value"><strong>Dibuat oleh:</strong> {{ $salesOrder->creator->name ?? '-' }}</div>
            </div>
        </div>

        {{-- Items Table --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;">No</th>
                    <th style="width: 40%;">Produk</th>
                    <th class="text-center" style="width: 12%;">Qty</th>
                    <th class="text-right" style="width: 18%;">Harga Satuan</th>
                    <th class="text-right" style="width: 20%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($salesOrder->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product->name ?? 'Product #' . $item->product_id }}</td>
                    <td class="text-center">{{ number_format((float) $item->quantity, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals-section">
            <div class="totals-spacer"></div>
            <div class="totals-box">
                <table class="totals-table">
                    <tr>
                        <td class="label">Subtotal</td>
                        <td class="value">Rp {{ number_format((float) $salesOrder->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if((float) $salesOrder->discount_amount > 0)
                    <tr>
                        <td class="label">Diskon</td>
                        <td class="value" style="color: #c0392b;">- Rp {{ number_format((float) $salesOrder->discount_amount, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if((float) $salesOrder->tax_amount > 0)
                    <tr>
                        <td class="label">Pajak</td>
                        <td class="value">Rp {{ number_format((float) $salesOrder->tax_amount, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr class="grand-total">
                        <td class="label">Grand Total</td>
                        <td class="value">Rp {{ number_format((float) $salesOrder->total_amount, 0, ',', '.') }}</td>
                    </tr>
                    @if((float) $salesOrder->paid_amount > 0)
                    <tr>
                        <td class="label">Terbayar</td>
                        <td class="value">Rp {{ number_format((float) $salesOrder->paid_amount, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Sisa</td>
                        <td class="value" style="color: #c0392b;">Rp {{ number_format((float) $salesOrder->total_amount - (float) $salesOrder->paid_amount, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        {{-- Payment Terms --}}
        <div class="payment-terms">
            <strong>Syarat Pembayaran</strong>
            @if($salesOrder->customer && $salesOrder->customer->payment_terms_days)
                Termin pembayaran: {{ $salesOrder->customer->payment_terms_days }} hari sejak tanggal invoice.
                Harap melakukan pembayaran sebelum {{ $salesOrder->order_date->copy()->addDays($salesOrder->customer->payment_terms_days)->format('d F Y') }}.
            @else
                Pembayaran dilakukan saat pesanan dikonfirmasi atau sesuai kesepakatan.
            @endif
            <br>Status Pembayaran: <strong>{{ ucfirst($salesOrder->payment_status ?? 'unpaid') }}</strong>
        </div>

        {{-- Notes --}}
        @if($salesOrder->notes)
        <div class="notes-section">
            <strong>Catatan:</strong>
            {{ $salesOrder->notes }}
        </div>
        @endif

        {{-- Footer --}}
        <div class="footer">
            Invoice ini dicetak secara otomatis oleh sistem {{ config('app.name', 'ERP System') }} pada {{ now()->format('d/m/Y H:i:s') }}.
        </div>
    </div>
</body>
</html>