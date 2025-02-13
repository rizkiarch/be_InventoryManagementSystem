<?php

namespace App\Http\Controllers\Api;

use App\Exports\StocksExport;
use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function transactionReport(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date', Carbon::now()
            ->startOfMonth()
            ->toDateString());

        $endDate = $request->input('end_date', Carbon::now()
            ->endOfMonth()
            ->toDateString());

        $queryTransaction = Transaction::with('item')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalProductIn = $queryTransaction->where('type', 'in')->sum('qty');
        $totalProductOut = $queryTransaction->where('type', 'out')->sum('qty');

        return response()->json([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_product_in' => $totalProductIn,
            'total_product_out' => $totalProductOut,
            'data' => $queryTransaction
        ]);
    }

    public function transactionToPdf(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date', Carbon::now()
            ->startOfMonth()
            ->toDateString());

        $endDate = $request->input('end_date', Carbon::now()
            ->endOfMonth()
            ->toDateString());

        $queryTransaction = Transaction::with('item')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalMasuk = $queryTransaction->where('type', 'in')->count();
        $totalKeluar = $queryTransaction->where('type', 'out')->count();

        $html = "
                <h2>Transaction Report</h2>
                <p>Periode: $startDate - $endDate</p>
                <p>Total Transaction Masuk: $totalMasuk</p>
                <p>Total Transaction Keluar: $totalKeluar</p>
                <table border='1' width='100%' cellpadding='5'>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Code Item</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>";

        foreach ($queryTransaction as $index => $trx) {
            $html .= "
                        <tr>
                             <td>" . ($index + 1) . "</td>
                            <td>{$trx->item->unique_code}</td>
                            <td>{$trx->item->name}</td>
                            <td>{$trx->type}</td>
                            <td>{$trx->qty}</td>
                            <td>{$trx->created_at->format('Y-m-d')}</td>
                        </tr>";
        }

        $html .= "
                    </tbody>
                </table>
            ";

        $pdf = Pdf::loadHTML($html);

        return $pdf->download("transaction-report_$startDate-$endDate.pdf");
    }

    public function transactionToExcel(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date', Carbon::now()
            ->startOfMonth()
            ->toDateString());

        $endDate = $request->input('end_date', Carbon::now()
            ->endOfMonth()
            ->toDateString());

        return Excel::download(new TransactionsExport($startDate, $endDate), "transaction-report.$startDate-$endDate.xlsx");
    }

    public function StockReport(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date', Carbon::now()
            ->startOfMonth()
            ->toDateString());

        $endDate = $request->input('end_date', Carbon::now()
            ->endOfMonth()
            ->toDateString());

        $stocks = Stock::with('item')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalQtyIn = $stocks->sum('qty_in');
        $totalQtyOut = $stocks->sum('qty_out');
        $totalStock = $stocks->sum('total');

        return response()->json([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_qty_in' => $totalQtyIn,
            'total_qty_out' => $totalQtyOut,
            'total_stock' => $totalStock,
            'data' => $stocks
        ]);
    }

    public function stockToPdf(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date', Carbon::now()
            ->startOfMonth()
            ->toDateString());

        $endDate = $request->input('end_date', Carbon::now()
            ->endOfMonth()
            ->toDateString());

        $stocks = Stock::with('item')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalQtyIn = $stocks->sum('qty_in');
        $totalQtyOut = $stocks->sum('qty_out');
        $totalStock = $stocks->sum('total');

        $html = "
            <h2>Stock Report</h2>
            <p>Periode: $startDate - $endDate</p>
            <p>Total Qty In: $totalQtyIn</p>
            <p>Total Qty Out: $totalQtyOut</p>
            <p>Total Stock: $totalStock</p>
            <table border='1' width='100%' cellpadding='5'>
                <thead>
                <tr>
                    <th>No</th>
                    <th>Code Item</th>
                    <th>Product</th>
                    <th>Qty In</th>
                    <th>Qty Out</th>
                    <th>Total</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>";

        foreach ($stocks as $index => $stock) {
            $html .= "
                <tr>
                    <td>" . ($index + 1) . "</td>
                    <td>{$stock->item->unique_code}</td>
                    <td>{$stock->item->name}</td>
                    <td>{$stock->qty_in}</td>
                    <td>{$stock->qty_out}</td>
                    <td>{$stock->total}</td>
                    <td>{$stock->created_at->format('Y-m-d')}</td>
                </tr>";
        }

        $html .= "
                </tbody>
            </table>
            ";

        $pdf = Pdf::loadHTML($html);

        return $pdf->download("stock-report_$startDate-$endDate.pdf");
    }

    public function stockToExcel(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date', Carbon::now()
            ->startOfMonth()
            ->toDateString());

        $endDate = $request->input('end_date', Carbon::now()
            ->endOfMonth()
            ->toDateString());

        return Excel::download(new StocksExport($startDate, $endDate), "stock-report.$startDate-$endDate.xlsx");
    }
}
