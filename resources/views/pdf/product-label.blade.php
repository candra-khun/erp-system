<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label {{ $product->sku }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
        .label {
            width: 48mm;
            height: 28mm;
            border: 1px dashed #999;
            padding: 2mm;
            margin: 0 0 2mm 0;
            page-break-inside: avoid;
            text-align: center;
        }
        .product-name { font-size: 10px; font-weight: bold; line-height: 1.2; margin-bottom: 1mm; }
        .barcode { margin: 1mm 0; }
        .barcode img { height: 12mm; }
        .code { font-size: 9px; letter-spacing: 1px; }
        .meta { font-size: 8px; color: #333; margin-top: 1mm; }
    </style>
</head>
<body>
@for($i = 0; $i < $copies; $i++)
    <div class="label">
        <div class="product-name">{{ $product->name }}</div>
        <div class="barcode">
            @if($barcodeSvg)
                <img src="{{ $barcodeSvg }}" alt="{{ $product->barcode }}">
            @else
                <span class="code">{{ $product->sku }}</span>
            @endif
        </div>
        @if($barcodeSvg)
            <div class="code">{{ $product->barcode }}</div>
        @endif
        <div class="meta">
            {{ $product->sku }}
            &bull;
            Rp {{ number_format((float) $product->selling_price, 0, ',', '.') }}
            @if($product->baseUnit)
                / {{ $product->baseUnit->symbol }}
            @endif
        </div>
    </div>
@endfor
</body>
</html>