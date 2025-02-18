<?php

namespace App\Http\Controllers\Api;

use App\Exports\StocksExport;
use App\Exports\TransactionsExport;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends BaseController
{
    public function transactionReport(Request $request)
    {
        if (!$this->hasPermission('*.read')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1',
            'search' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $perPage = $request->input('per_page', Transaction::where('status', 'success')->count());
        $search = $request->input('search', '');

        $startDate = $request->input('start_date') ?? Carbon::now()
            ->startOfMonth()
            ->toDateString();
        $endDate = $request->input('end_date') ?? Carbon::now()
            ->endOfMonth()
            ->toDateString();

        $queryTransaction = Transaction::with('item')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->where('status', 'success')
            ->when($search, function ($query) use ($search) {
                return $query->whereHas('item', function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('unique_code', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('updated_at', 'desc');

        $totalProductIn = (clone $queryTransaction)->where('type', 'in')->sum('qty');
        $totalProductOut = (clone $queryTransaction)->where('type', 'out')->sum('qty');

        $paginatedData = $queryTransaction->paginate($perPage);

        return response()->json([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_product_in' => $totalProductIn,
            'total_product_out' => $totalProductOut,
            'current_page' => $paginatedData->currentPage(),
            'per_page' => $paginatedData->perPage(),
            'total' => $paginatedData->total(),
            'last_page' => $paginatedData->lastPage(),
            'next_page_url' => $paginatedData->nextPageUrl(),
            'prev_page_url' => $paginatedData->previousPageUrl(),
            'data' => $paginatedData->items()
        ]);
    }

    public function transactionToPdf(Request $request)
    {
        if (!$this->hasPermission('*.read')) {
            return $this->errorResponse('Unauthorized', 403);
        }

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
            ->where('status', 'success')
            ->orderBy('created_at', 'desc')
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
                            <th>Transaction ID</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>";

        foreach ($queryTransaction as $index => $trx) {
            $transactionId = 'TRX' . $trx->item->unique_code . $trx->id;

            $html .= "
                        <tr>
                             <td>" . ($index + 1) . "</td>
                            <td>{$transactionId}</td>
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
        if (!$this->hasPermission('*.read')) {
            return $this->errorResponse('Unauthorized', 403);
        }

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
        if (!$this->hasPermission('*.read')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string',
        ]);

        $perPage = $request->input('per_page', Transaction::where('status', 'success')->count());
        $page = $request->input('page', 1);
        $search = $request->input('search', '');

        $startDate = $request->input('start_date') ?? Carbon::now()
            ->startOfMonth()
            ->toDateString();
        $endDate = $request->input('end_date') ?? Carbon::now()
            ->endOfMonth()
            ->toDateString();

        // $query  = Stock::with('item')
        //     ->whereBetween('stocks.updated_at', [$startDate, $endDate])
        //     ->join('items', 'stocks.item_id', '=', 'items.id')
        //     ->orderBy('items.name', 'asc');
        $query  = Stock::with('item', 'item.transactions')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->orderBy('updated_at', 'desc');

        if (!empty($search)) {
            $query->whereHas('item', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('unique_code', 'like', "%{$search}%");
            });
        }

        $totalQtyIn = $query->sum('qty_in');
        $totalQtyOut = $query->sum('qty_out');
        $totalStock = $query->sum('total');

        $stocks = $query->paginate($perPage, ['*'], 'page', $page);

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
        if (!$this->hasPermission('*.read')) {
            return $this->errorResponse('Unauthorized', 403);
        }

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
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->orderBy('updated_at', 'desc')
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
                    <th>Stock ID</th>
                    <th>Product</th>
                    <th>Qty In</th>
                    <th>Qty Out</th>
                    <th>Total</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>";

        foreach ($stocks as $index => $stock) {
            $transactionId = 'TRXS' . $stock->item->unique_code . $stock->id;
            $html .= "
                <tr>
                    <td>" . ($index + 1) . "</td>
                    <td>{$transactionId}</td>
                    <td>{$stock->item->name}</td>
                    <td>{$stock->qty_in}</td>
                    <td>{$stock->qty_out}</td>
                    <td>{$stock->total}</td>
                    <td>{$stock->updated_at->format('Y-m-d')}</td>
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
        if (!$this->hasPermission('*.read')) {
            return $this->errorResponse('Unauthorized', 403);
        }

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

    public function productCount(Request $request)
    {
        if (!$this->hasPermission('*.read')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $totalProduct = Item::all()->count();

        return response()->json([
            'total_product' => $totalProduct
        ]);
    }

    public function userCount(Request $request)
    {
        if (!$this->hasPermission('*.read')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $totalUser = User::all()->count();

        return response()->json([
            'total_user' => $totalUser
        ]);
    }
}
