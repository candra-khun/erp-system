<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\SalesTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfController extends Controller
{
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
}
