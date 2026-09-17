<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Surat Jalan {{ $shipment->shipment_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; padding: 24px; }
        .header { width: 100%; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .header table { width: 100%; }
        .header td { vertical-align: top; }
        .company-name { font-size: 16px; font-weight: bold; }
        .company-info { font-size: 10px; color: #444; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 18px; letter-spacing: 1px; }
        .doc-title .doc-number { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .meta { width: 100%; margin-bottom: 12px; }
        .meta td { vertical-align: top; padding: 2px 0; font-size: 11px; }
        .meta .label { color: #555; width: 110px; }
        .box { border: 1px solid #ccc; padding: 8px 10px; }
        .box h3 { font-size: 10px; text-transform: uppercase; color: #666; margin-bottom: 4px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th, table.items td { border: 1px solid #333; padding: 5px 6px; }
        table.items th { background: #f0f0f0; font-size: 10px; text-transform: uppercase; text-align: left; }
        table.items td.right { text-align: right; }
        table.items td.center { text-align: center; }
        .totals { width: 60%; margin-left: 40%; margin-top: 8px; }
        .totals td { padding: 3px 0; }
        .totals td.right { text-align: right; }
        .signatures { width: 100%; margin-top: 36px; }
        .signatures td { width: 33%; text-align: center; vertical-align: bottom; }
        .signatures .line { border-top: 1px solid #333; margin-top: 48px; padding-top: 4px; font-size: 10px; }
        .footer { margin-top: 20px; font-size: 9px; color: #666; text-align: center; }
        .status-badge { display: inline-block; padding: 2px 8px; border: 1px solid #333; border-radius: 8px; font-size: 10px; }
    </style>
</head>
<body>
    @php
        $so = $shipment->salesOrder;
        $warehouse = $so?->warehouse;
    @endphp

    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="company-name">{{ $warehouse->name ?? config('app.name', 'PERUSAHAAN') }}</div>
                    <div class="company-info">
                        {{ $warehouse->address ?? config('app.store_address', '') }}<br>
                        @if(!empty($warehouse->phone)) Telp: {{ $warehouse->phone }} @endif
                    </div>
                </td>
                <td class="doc-title">
                    <h1>SURAT JALAN</h1>
                    <div class="doc-number">{{ $shipment->shipment_number }}</div>
                    <div class="company-info">Tanggal: {{ $shipment->created_at?->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td width="50%">
                <div class="box">
                    <h3>Pengirim</h3>
                    <div><strong>{{ $warehouse->name ?? '-' }}</strong></div>
                    <div>{{ $warehouse->address ?? '-' }}</div>
                    @if(!empty($warehouse->phone))<div>Telp: {{ $warehouse->phone }}</div>@endif
                </div>
            </td>
            <td width="50%">
                <div class="box">
                    <h3>Penerima</h3>
                    <div><strong>{{ $shipment->recipient_name ?: ($so?->customer?->name ?? '-') }}</strong></div>
                    <div>{{ $shipment->destination_address ?: ($so?->customer?->address ?? '-') }}</div>
                    @if($shipment->recipient_phone)<div>Telp: {{ $shipment->recipient_phone }}</div>@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="label">No. Sales Order</td>
            <td>: {{ $so?->so_number ?? '-' }}</td>
            <td class="label">Status</td>
            <td>: <span class="status-badge">{{ $shipment->status->label() }}</span></td>
        </tr>
        <tr>
            <td class="label">Kurir / Ekspedisi</td>
            <td>: {{ $shipment->courier?->name ?? '—' }}{{ $shipment->courier ? ' ('.$shipment->courier->type.')' : '' }}</td>
            <td class="label">Berat Total</td>
            <td>: {{ number_format((float) $shipment->total_weight_kg, 2, ',', '.') }} kg</td>
        </tr>
        <tr>
            <td class="label">Ongkos Kirim</td>
            <td>: Rp {{ number_format((float) $shipment->shipping_cost, 0, ',', '.') }}</td>
            <td class="label">Dibuat oleh</td>
            <td>: {{ $shipment->creator?->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 32px;">No</th>
                <th>Produk</th>
                <th style="width: 90px;">SKU</th>
                <th style="width: 70px;" class="center">Qty</th>
                <th style="width: 70px;">Satuan</th>
                <th style="width: 60px;" class="center">Dikirim</th>
            </tr>
        </thead>
        <tbody>
            @forelse($so?->items ?? [] as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $item->product?->name ?? 'Produk #'.$item->product_id }}</td>
                    <td>{{ $item->product?->sku ?? '-' }}</td>
                    <td class="center">{{ number_format((float) $item->quantity, 0, ',', '.') }}</td>
                    <td>{{ $item->product?->baseUnit?->symbol ?? $item->product?->baseUnit?->name ?? '-' }}</td>
                    <td class="center">☐</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center">Tidak ada item.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($shipment->notes)
        <p style="margin-top: 10px; font-size: 10px;"><strong>Catatan:</strong> {{ $shipment->notes }}</p>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="line">Pengirim</div>
            </td>
            <td>
                <div class="line">Kurir / Ekspedisi</div>
            </td>
            <td>
                <div class="line">Penerima</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dokumen ini merupakan bukti pengiriman barang. Mohon periksa kondisi barang saat diterima.<br>
        Dicetak pada {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>