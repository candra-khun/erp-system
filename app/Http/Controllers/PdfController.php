<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesTransaction;
use App\Models\Shipment;
use App\Services\BarcodeGeneratorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PdfController extends Controller
{
    public function __construct(private BarcodeGeneratorService $barcodeGenerator) {}

    public function posReceipt(int $id): Response
    {
        $transaction = SalesTransaction::with(['items.product', 'cashier', 'customer'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.pos-receipt', compact('transaction'))
            ->setPaper([0, 0, 226.77, 841.89], 'portrait'); // ~80mm width in points

        return $pdf->stream('struk-'.$transaction->transaction_number.'.pdf');
    }

    public function salesOrderInvoice(int $id): Response
    {
        $salesOrder = SalesOrder::with(['items.product', 'customer', 'warehouse', 'creator'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.invoice', compact('salesOrder'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('invoice-'.$salesOrder->so_number.'.pdf');
    }

    /**
     * Cetak surat jalan (delivery note) PDF untuk pengiriman.
     */
    public function shipmentDeliveryNote(int $id): Response
    {
        $shipment = Shipment::with([
            'salesOrder.customer',
            'salesOrder.warehouse',
            'salesOrder.items.product.baseUnit',
            'courier',
            'creator',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.delivery-note', compact('shipment'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('surat-jalan-'.$shipment->shipment_number.'.pdf');
    }

    /**
     * Cetak label barcode untuk satu produk (per SKU).
     */
    public function productLabel(Request $request, int $id): Response
    {
        $product = Product::with('baseUnit')->findOrFail($id);

        $copies = (int) $request->query('copies', 1);
        $copies = max(1, min($copies, 50));

        $barcodeSvg = $product->barcode
            ? $this->barcodeGenerator->generateBase64($product->barcode)
            : null;

        $pdf = Pdf::loadView('pdf.product-label', [
            'product' => $product,
            'copies' => $copies,
            'barcodeSvg' => $barcodeSvg,
        ])->setPaper([0, 0, 141.73, 85.04], 'landscape'); // 50mm x 30mm

        return $pdf->stream('label-'.$product->sku.'.pdf');
    }
}
