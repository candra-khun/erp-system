<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesTransaction;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    /**
     * Generate PDF from a Blade view and return the PDF instance for download/stream.
     *
     * @param  array<string, mixed>  $data
     */
    public function generateFromView(string $view, array $data = [], string $paperSize = 'a4'): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView($view, $data)->setPaper($paperSize);
    }

    /**
     * Generate receipt PDF for a POS transaction.
     * Uses a simple inline HTML template as fallback until Blade views are created.
     */
    public function generateReceipt(SalesTransaction $transaction): \Barryvdh\DomPDF\PDF
    {
        $transaction->load(['items.product', 'warehouse', 'cashier', 'payments']);

        $html = $this->buildReceiptHtml($transaction);

        return Pdf::loadHTML($html)->setPaper([0, 0, 226, 800], 'portrait');
    }

    /**
     * Generate invoice PDF for a Sales Order.
     */
    public function generateInvoice(SalesOrder $order): \Barryvdh\DomPDF\PDF
    {
        $order->load(['items.product', 'customer', 'warehouse', 'creator']);

        $html = $this->buildInvoiceHtml($order);

        return Pdf::loadHTML($html)->setPaper('a4');
    }

    private function buildReceiptHtml(SalesTransaction $tx): string
    {
        $itemsHtml = '';
        foreach ($tx->items as $item) {
            $itemsHtml .= sprintf(
                '<tr><td>%s</td><td style="text-align:right">%s x %s</td><td style="text-align:right">%s</td></tr>',
                e($item->product?->name ?? 'Item'),
                number_format((float) $item->quantity, 0),
                number_format((float) $item->unit_price, 0, ',', '.'),
                number_format((float) $item->subtotal, 0, ',', '.')
            );
        }

        return <<<HTML
        <html><head><style>
            body { font-family: monospace; font-size: 10px; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 1px 0; }
            .center { text-align: center; }
            .right { text-align: right; }
            hr { border: none; border-top: 1px dashed #000; }
        </style></head><body>
            <div class="center"><strong>{$tx->warehouse->name}</strong><br>
            {$tx->warehouse->address}<br><br>
            <strong>STRUK PENJUALAN</strong><br>
            No: {$tx->transaction_number}<br>
            Tanggal: {$tx->created_at->format('d/m/Y H:i')}<br>
            Kasir: {$tx->cashier->name}<br></div>
            <hr>
            <table>{$itemsHtml}</table>
            <hr>
            <table>
                <tr><td>Subtotal</td><td class="right">{$tx->subtotal}</td></tr>
                <tr><td>Diskon</td><td class="right">{$tx->discount_amount}</td></tr>
                <tr><td>Pajak</td><td class="right">{$tx->tax_amount}</td></tr>
                <tr><td><strong>TOTAL</strong></td><td class="right"><strong>{$tx->total_amount}</strong></td></tr>
                <tr><td>Bayar ({$tx->payment_method})</td><td class="right">{$tx->paid_amount}</td></tr>
                <tr><td>Kembali</td><td class="right">{$tx->change_amount}</td></tr>
            </table>
            <hr>
            <div class="center">Terima kasih atas kunjungan Anda</div>
        </body></html>
        HTML;
    }

    private function buildInvoiceHtml(SalesOrder $order): string
    {
        $itemsHtml = '';
        $no = 1;
        foreach ($order->items as $item) {
            $itemsHtml .= sprintf(
                '<tr><td>%d</td><td>%s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td></tr>',
                $no++,
                e($item->product?->name ?? 'Item'),
                number_format((float) $item->quantity, 0),
                number_format((float) $item->unit_price, 0, ',', '.'),
                number_format((float) $item->subtotal, 0, ',', '.')
            );
        }

        return <<<HTML
        <html><head><style>
            body { font-family: sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #333; padding: 5px; }
            th { background: #f0f0f0; }
            .right { text-align: right; }
        </style></head><body>
            <h2>INVOICE</h2>
            <p>No: {$order->so_number}<br>
            Tanggal: {$order->order_date}<br>
            Pelanggan: {$order->customer->name}<br>
            Gudang: {$order->warehouse->name}</p>
            <table>
                <thead><tr><th>#</th><th>Produk</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
                <tbody>{$itemsHtml}</tbody>
            </table>
            <table style="border:none">
                <tr><td style="border:none;width:70%"></td><td class="right">Subtotal: {$order->subtotal}</td></tr>
                <tr><td style="border:none"></td><td class="right">Diskon: {$order->discount_amount}</td></tr>
                <tr><td style="border:none"></td><td class="right">Pajak: {$order->tax_amount}</td></tr>
                <tr><td style="border:none"></td><td class="right"><strong>Total: {$order->total_amount}</strong></td></tr>
            </table>
        </body></html>
        HTML;
    }
}
